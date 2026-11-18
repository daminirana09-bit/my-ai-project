<?php

namespace App\Services\Hr;

use App\Models\AttendanceDay;
use App\Models\CompanySetting;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** Leave days, balances, and what approval does to attendance. */
class LeaveService
{
    /** Working days between two dates: weekly offs and holidays are not counted. */
    public function workingDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $off = CompanySetting::current()->weeklyOffDays();
        $out = [];
        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $d) {
            if (! in_array($d->dayOfWeek, $off, true) && ! Holiday::isHoliday($d)) {
                $out[] = $d->toDateString();
            }
        }

        return $out;
    }

    public function countDays(CarbonInterface $from, CarbonInterface $to, bool $half): float
    {
        $n = count($this->workingDates($from, $to));

        return $half ? min(0.5, $n * 0.5) : (float) $n;
    }

    /** Leave allowed in a year; someone who joined during the year gets their share of it. */
    public function quota(User $u, LeaveType $t, int $year): ?float
    {
        if ($t->yearly_quota === null) {
            return null;
        }
        $q = (float) $t->yearly_quota;
        $doj = $u->profile?->date_of_joining;
        if ($doj && $doj->year === $year) {
            $q = floor($q * (13 - $doj->month) / 12 * 2) / 2; // to the half day
        } elseif ($doj && $doj->year > $year) {
            $q = 0;
        }

        return $q;
    }

    /** Days taken or asked for in the year (approved and waiting). */
    public function used(User $u, LeaveType $t, int $year, ?int $except = null): float
    {
        return (float) LeaveRequest::where('user_id', $u->id)->where('leave_type_id', $t->id)->whereIn('status', ['pending', 'approved'])
            ->whereYear('from_date', $year)->when($except, fn ($q) => $q->whereKeyNot($except))->sum('days');
    }

    /** @return array<int, array{type: LeaveType, quota: ?float, used: float, left: ?float}> */
    public function balances(User $u, int $year): array
    {
        return LeaveType::ordered()->where('active', true)->get()->map(function (LeaveType $t) use ($u, $year) {
            $quota = $this->quota($u, $t, $year);
            $used = $this->used($u, $t, $year);

            return ['type' => $t, 'quota' => $quota, 'used' => $used, 'left' => $quota === null ? null : max(0, $quota - $used)];
        })->all();
    }

    /** @return string|null why it cannot be asked for, or null when fine */
    public function problem(User $u, LeaveType $t, CarbonImmutable $from, CarbonImmutable $to, bool $half): ?string
    {
        if ($to->lt($from)) {
            return __('The last day is before the first day.');
        }
        if ($from->year !== $to->year) {
            return __('Ask for leave in each year separately.');
        }
        if ($half && ! $from->isSameDay($to)) {
            return __('A half day is for one date only.');
        }
        $days = $this->countDays($from, $to, $half);
        if ($days <= 0) {
            return __('These dates are weekly offs or holidays.');
        }
        $clash = LeaveRequest::where('user_id', $u->id)->whereIn('status', ['pending', 'approved'])
            ->where('from_date', '<=', $to->toDateString())->where('to_date', '>=', $from->toDateString())->exists();
        if ($clash) {
            return __('You already have leave on some of these dates.');
        }
        $quota = $this->quota($u, $t, $from->year);
        if ($quota !== null && $this->used($u, $t, $from->year) + $days > $quota) {
            return __('Not enough :type left: :n days.', ['type' => $t->name, 'n' => rtrim(rtrim(number_format(max(0, $quota - $this->used($u, $t, $from->year)), 1), '0'), '.')]);
        }

        return null;
    }

    /** Approved leave shows as "Leave" in attendance, so the day is never marked absent. */
    public function approve(LeaveRequest $r, User $by, ?string $note): void
    {
        DB::transaction(function () use ($r, $by, $note) {
            $r->update(['status' => 'approved', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
            $label = $r->type->code.($r->half_day ? ' (½)' : '');
            foreach ($this->workingDates($r->from_date, $r->to_date) as $d) {
                app(AttendanceService::class)->override($r->user, CarbonImmutable::parse($d), 'leave', mb_substr(__('Leave approved').': '.$label, 0, 190));
            }
        });
    }

    /** Taking back an approved leave puts attendance back as worked out from the punches. */
    public function undo(LeaveRequest $r): void
    {
        foreach ($this->workingDates($r->from_date, $r->to_date) as $d) {
            AttendanceDay::where('user_id', $r->user_id)->whereDate('date', $d)->where('status', 'leave')->where('manual', true)
                ->update(['manual' => false, 'status' => 'absent', 'remark' => null]);
            if (CarbonImmutable::parse($d)->lte(today())) {
                app(AttendanceService::class)->recalculate($r->user, CarbonImmutable::parse($d));
            } else {
                AttendanceDay::where('user_id', $r->user_id)->whereDate('date', $d)->where('manual', false)->delete();
            }
        }
    }

    /** Who may approve this person's leave and claims: their manager (or anyone above), and admins; not themselves, except a sole admin. */
    public static function canApprove(User $approver, User $of): bool
    {
        if ($approver->id === $of->id) {
            // The only admin (usually the owner) has nobody above them.
            return $approver->isAdmin() && ! User::active()->where('role', 'admin')->whereKeyNot($approver->id)->exists();
        }

        return $approver->isAdmin() || ($approver->allows('approve_leaves') && $approver->canSeeUser($of->id));
    }

    /** People who get the request: the person's manager if they can approve, else every admin. */
    public static function approversOf(User $u)
    {
        $m = $u->manager_id ? User::active()->find($u->manager_id) : null;

        if ($m && self::canApprove($m, $u)) {
            return collect([$m]);
        }
        $admins = User::active()->where('role', 'admin')->whereKeyNot($u->id)->get();

        return $admins->isNotEmpty() ? $admins : collect([$u])->filter(fn ($x) => self::canApprove($x, $u));
    }
}
