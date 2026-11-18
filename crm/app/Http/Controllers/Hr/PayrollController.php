<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\EmployeeProfile;
use App\Models\ExpenseClaim;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\HrAlert;
use App\Services\Hr\PayrollCalculator;
use App\Services\Sheets;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Monthly salary: draft from attendance and leave, adjust, finalise, payslips and bank sheet. */
class PayrollController extends Controller
{
    public function __construct(private PayrollCalculator $calc) {}

    public function index()
    {
        return view('hr.payroll', ['runs' => PayrollRun::withCount('payslips')->orderByDesc('month')->get(),
            'ready' => EmployeeProfile::with('user')->get()->filter(fn ($p) => $p->user?->active && $p->monthlyGross() > 0)->count()]);
    }

    /** Make (or remake) the draft for a month. */
    public function store(Request $request)
    {
        $month = CarbonImmutable::parse($request->validate(['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')]])['month'].'-01');
        $run = PayrollRun::firstOrCreate(['month' => $month->toDateString()], ['status' => 'draft', 'created_by' => $request->user()->id]);
        if ($run->isFinal()) {
            return redirect()->route('hr.payroll.show', $run)->withErrors(['run' => __('This month is already final.')]);
        }
        $n = $this->generate($run);
        AuditLog::record('payroll.draft', $month->format('Y-m'), ['people' => $n]);

        return redirect()->route('hr.payroll.show', $run)->with('ok', __('Draft salary for :n people. Check, adjust and then finalise.', ['n' => $n]));
    }

    public function show(PayrollRun $run)
    {
        $slips = $run->payslips()->with('user:id,name')->get()->sortBy(fn ($s) => $s->user?->name)->values();

        return view('hr.payroll-run', ['run' => $run, 'slips' => $slips,
            'totals' => ['gross' => $slips->sum(fn ($s) => $s->v('gross')), 'net' => $slips->sum(fn ($s) => $s->v('net')),
                'deductions' => $slips->sum(fn ($s) => $s->v('total_deductions')), 'reimbursement' => $slips->sum(fn ($s) => $s->v('reimbursement')),
                'pf' => $slips->sum(fn ($s) => $s->v('deductions')['pf'] ?? 0) + $slips->sum(fn ($s) => $s->v('employer')['pf'] ?? 0),
                'esi' => $slips->sum(fn ($s) => $s->v('deductions')['esi'] ?? 0) + $slips->sum(fn ($s) => $s->v('employer')['esi'] ?? 0)]]);
    }

    public function recalculate(PayrollRun $run)
    {
        abort_if($run->isFinal(), 403);
        $n = $this->generate($run);

        return back()->with('ok', __('Recalculated for :n people.', ['n' => $n]));
    }

    /** Incentive, other deduction and a note for one person (draft only). */
    public function adjust(Request $request, PayrollRun $run, Payslip $payslip)
    {
        abort_if($run->isFinal() || $payslip->payroll_run_id !== $run->id, 403);
        $data = $request->validate(['incentive' => ['nullable', 'numeric', 'min:0', 'max:1000000'], 'deduction' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200']]);
        $payslip->update(['adjust' => $data, 'data' => $this->calc->calculate($payslip->user, CarbonImmutable::parse($run->month), $data)]);

        return back()->with('ok', __('Saved for :name.', ['name' => $payslip->user->name]));
    }

    public function finalize(Request $request, PayrollRun $run)
    {
        abort_if($run->isFinal(), 403);
        DB::transaction(function () use ($run) {
            foreach ($run->payslips()->get() as $s) {
                ExpenseClaim::whereIn('id', $s->v('claim_ids', []))->where('status', 'approved')->update(['status' => 'paid', 'payslip_id' => $s->id]);
            }
            $run->update(['status' => 'final', 'finalized_at' => now()]);
        });
        foreach ($run->payslips()->with('user')->get() as $s) {
            $s->user?->notify(new HrAlert('payslip', ['payslip_id' => $s->id, 'month' => $run->month->format('M Y')]));
        }
        AuditLog::record('payroll.final', $run->month->format('Y-m'));

        return back()->with('ok', __('Salary for :m is final. Everyone can see their payslip.', ['m' => $run->month->format('M Y')]));
    }

    public function destroy(PayrollRun $run)
    {
        abort_if($run->isFinal(), 403);
        $run->delete();

        return redirect()->route('hr.payroll')->with('ok', __('Draft deleted.'));
    }

    /** Salary register or bank transfer sheet. */
    public function export(Request $request, PayrollRun $run)
    {
        $type = $request->query('type') === 'bank' ? 'bank' : 'register';
        $slips = $run->payslips()->with('user.profile')->get()->sortBy(fn ($s) => $s->user?->name);
        if ($type === 'bank') {
            $header = ['Employee code', 'Name', 'Account name', 'Account number', 'IFSC', 'Bank', 'Amount'];
            $rows = $slips->map(fn ($s) => [$s->v('emp_code'), $s->user->name, $s->user->profile?->field('bank', 'account_name') ?: $s->user->name,
                $s->user->profile?->field('bank', 'account_no'), $s->user->profile?->field('bank', 'ifsc'), $s->user->profile?->field('bank', 'bank_name'), (float) $s->v('net')]);
        } else {
            $header = ['Employee code', 'Name', 'Designation', 'Days in month', 'Paid days', 'Loss of pay days', 'Basic', 'HRA', 'Conveyance', 'Special', 'Other',
                'Gross', 'Incentive', 'Reimbursement', 'PF', 'ESI', 'Professional tax', 'TDS', 'Other deduction', 'Total deductions', 'Net pay', 'Employer PF', 'Employer ESI'];
            $rows = $slips->map(fn ($s) => [$s->v('emp_code'), $s->user->name, $s->v('designation'), $s->v('days_in_month'), $s->v('paid_days'), $s->v('lop_days'),
                ...array_values($s->v('earnings', [])), $s->v('gross'), $s->v('incentive'), $s->v('reimbursement'),
                ...array_values($s->v('deductions', [])), $s->v('total_deductions'), $s->v('net'), $s->v('employer')['pf'] ?? 0, $s->v('employer')['esi'] ?? 0]);
        }
        $path = Sheets::write('xlsx', $header, $rows->values()->all());
        AuditLog::record('payroll.export', $run->month->format('Y-m'), ['type' => $type]);

        return response()->download($path, ($type === 'bank' ? 'bank-transfer-' : 'salary-register-').$run->month->format('Y-m').'.xlsx')->deleteFileAfterSend();
    }

    /** Payslip PDF: the person's own (once final), or any for an admin. */
    public function payslip(Request $request, Payslip $payslip)
    {
        $me = $request->user();
        abort_unless($me->isAdmin() || ($payslip->user_id === $me->id && $payslip->run->isFinal()), 404);
        $c = CompanySetting::current();
        $logo = null;
        if ($c->logo_path && Storage::disk('local')->exists($c->logo_path)) {
            $file = Storage::disk('local')->path($c->logo_path);
            $logo = 'data:'.mime_content_type($file).';base64,'.base64_encode(file_get_contents($file));
        }
        $opt = new Options;
        $opt->set('isRemoteEnabled', false);
        $opt->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opt);
        $pdf->loadHtml(view('hr.payslip-pdf', ['c' => $c, 'logo' => $logo, 's' => $payslip, 'run' => $payslip->run, 'words' => PayrollCalculator::inWords((int) $payslip->v('net'))])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        AuditLog::record('payslip.open', "payslip:{$payslip->id}");

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="payslip-'.$payslip->run->month->format('Y-m').'-'.\Illuminate\Support\Str::slug($payslip->user->name).'.pdf"']);
    }

    private function generate(PayrollRun $run): int
    {
        $month = CarbonImmutable::parse($run->month);
        $keep = [];
        foreach (User::with('profile')->get() as $u) {
            if (! PayrollCalculator::eligible($u, $month)) {
                continue;
            }
            $slip = Payslip::firstOrNew(['payroll_run_id' => $run->id, 'user_id' => $u->id]);
            $slip->data = $this->calc->calculate($u, $month, $slip->adjust ?? []);
            $slip->save();
            $keep[] = $slip->id;
        }
        $run->payslips()->whereNotIn('id', $keep)->delete();

        return count($keep);
    }
}
