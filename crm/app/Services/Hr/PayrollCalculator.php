<?php

namespace App\Services\Hr;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\EmployeeProfile;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * One person's salary for one month.
 * - Paid days = days in the month − days not yet joined / already left − loss of pay.
 * - Loss of pay: absent working days (½ for half day or a single punch) when the person uses
 *   attendance that month, plus approved leave without pay. Weekly offs, holidays and paid
 *   leave are paid.
 * - PF 12% of basic up to ₹15,000; ESI 0.75% when monthly gross is up to ₹21,000; professional
 *   tax and TDS as set for the person. Approved expense claims are paid with the salary.
 */
class PayrollCalculator
{
    public function __construct(private LeaveService $leaves) {}

    public static function eligible(User $u, CarbonImmutable $month): bool
    {
        $p = $u->profile;
        if (! $p || $p->monthlyGross() <= 0) {
            return false;
        }
        if ($p->date_of_joining && $p->date_of_joining->gt($month->endOfMonth())) {
            return false;
        }

        return ! ($p->status === 'left' && $p->exit_date && $p->exit_date->lt($month));
    }

    public function calculate(User $u, CarbonImmutable $month, array $adjust = []): array
    {
        $co = CompanySetting::current();
        $p = $u->profile ?? new EmployeeProfile;
        $start = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay();
        $daysInMonth = $start->daysInMonth;

        $from = $p->date_of_joining && $p->date_of_joining->gt($start) ? CarbonImmutable::parse($p->date_of_joining) : $start;
        $to = $p->exit_date && $p->exit_date->lt($end) ? CarbonImmutable::parse($p->exit_date) : $end;
        $employedDays = $to->lt($from) ? 0 : (int) $from->diffInDays($to) + 1;

        $usesAttendance = AttendancePunch::where('user_id', $u->id)->whereBetween('punched_at', [$start, $end->endOfDay()])->exists();
        $working = $employedDays ? $this->leaves->workingDates($from, $to) : [];
        $absent = 0.0;
        if ($usesAttendance && $working) {
            $rows = AttendanceDay::where('user_id', $u->id)->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])->get()
                ->keyBy(fn ($d) => substr((string) $d->getRawOriginal('date'), 0, 10));
            foreach ($working as $d) {
                $absent += match ($rows[$d]->status ?? null) {
                    'absent' => 1.0,
                    'half_day', 'single_swipe' => 0.5,
                    default => 0.0,
                };
            }
        }
        $unpaid = 0.0;
        foreach (LeaveRequest::with('type')->where('user_id', $u->id)->where('status', 'approved')
            ->where('from_date', '<=', $to->toDateString())->where('to_date', '>=', $from->toDateString())->get() as $r) {
            if ($r->type?->paid) {
                continue;
            }
            $dates = array_intersect($this->leaves->workingDates($r->from_date, $r->to_date), $working);
            $unpaid += $r->half_day ? min(0.5, count($dates) * 0.5) : count($dates);
        }
        $lop = min($employedDays, $absent + $unpaid);
        $paidDays = max(0, $employedDays - $lop);
        $factor = $daysInMonth ? $paidDays / $daysInMonth : 0;

        $earnings = [];
        foreach (array_keys(EmployeeProfile::EARNINGS) as $k) {
            $earnings[$k] = (int) round((float) $p->pay($k, 0) * $factor);
        }
        $gross = array_sum($earnings);
        $fullGross = $p->monthlyGross();

        $pf = $p->pay('pf') ? (int) round(min($earnings['basic'], $co->hr('pf_ceiling')) * $co->hr('pf_rate') / 100) : 0;
        $esiApplies = $p->pay('esi') && $fullGross <= $co->hr('esi_ceiling');
        $esi = $esiApplies ? (int) ceil($gross * $co->hr('esi_rate') / 100) : 0;
        $pt = $gross > 0 ? (int) round((float) $p->pay('pt', $co->hr('pt_default'))) : 0;
        $tds = $gross > 0 ? (int) round((float) $p->pay('tds', 0)) : 0;
        $other = (int) round((float) ($adjust['deduction'] ?? 0));
        $incentive = (int) round((float) ($adjust['incentive'] ?? 0));

        $claims = ExpenseClaim::where('user_id', $u->id)->where('status', 'approved')->whereNull('payslip_id')->where('date', '<=', $end->toDateString())->get(['id', 'amount']);
        $reimburse = (int) round((float) $claims->sum('amount'));

        $deductions = ['pf' => $pf, 'esi' => $esi, 'pt' => $pt, 'tds' => $tds, 'other' => $other];
        $totalDeductions = array_sum($deductions);

        return [
            'name' => $u->name, 'emp_code' => $p->emp_code, 'designation' => $p->designation, 'department' => $p->department, 'headquarter' => $p->headquarter,
            'doj' => $p->date_of_joining?->toDateString(), 'pan' => $p->field('ids', 'pan'), 'uan' => $p->field('ids', 'uan'), 'esic' => $p->field('ids', 'esic'),
            'bank' => $p->field('bank', 'bank_name'), 'account' => $p->maskedAccount(), 'ifsc' => $p->field('bank', 'ifsc'),
            'days_in_month' => $daysInMonth, 'employed_days' => $employedDays, 'lop_days' => $lop, 'paid_days' => $paidDays,
            'absent_days' => $absent, 'unpaid_leave_days' => $unpaid, 'uses_attendance' => $usesAttendance,
            'earnings' => $earnings, 'gross' => $gross, 'incentive' => $incentive, 'reimbursement' => $reimburse, 'claim_ids' => $claims->pluck('id')->all(),
            'deductions' => $deductions, 'total_deductions' => $totalDeductions,
            'net' => max(0, $gross + $incentive + $reimburse - $totalDeductions),
            'employer' => ['pf' => $pf, 'esi' => $esiApplies ? (int) ceil($gross * $co->hr('esi_employer_rate') / 100) : 0],
            'note' => $adjust['note'] ?? null,
        ];
    }

    /** Indian way of writing a rupee amount in words, for the payslip. */
    public static function inWords(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn (int $x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)].' '.$ones[$x % 10]);
        $three = fn (int $x) => trim(($x >= 100 ? $ones[intdiv($x, 100)].' Hundred ' : '').$two($x % 100));
        if ($n === 0) {
            return 'Zero Rupees Only';
        }
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $word]) {
            if ($n >= $div) {
                $q = intdiv($n, $div);
                $parts[] = ($q < 100 ? $two($q) : $three($q)).' '.$word;
                $n %= $div;
            }
        }
        if ($n > 0) {
            $parts[] = $three($n);
        }

        return trim(implode(' ', $parts)).' Rupees Only';
    }
}
