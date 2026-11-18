<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\MasterOption;
use App\Models\User;
use App\Services\Reports;
use App\Services\Sheets;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Deals report and the monthly sales report. Everyone sees only their own team's numbers. */
class ReportController extends Controller
{
    public function __construct(private Reports $reports) {}

    private function allowed(Request $request): User
    {
        $me = $request->user();
        abort_unless($me->allows('view_analytics'), 403);

        return $me;
    }

    public function deals(Request $request)
    {
        $me = $this->allowed($request);
        [$from, $to, $leads] = $this->dealFilters($request, $me);
        if ($request->query('export') === 'xlsx') {
            return $this->exportDeals($leads, $from, $to);
        }

        return view('reports.deals', ['r' => $this->reports->deals($leads, $from, $to), 'from' => $from, 'to' => $to,
            'owners' => $me->isRep() ? collect() : User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']), 'sources' => MasterOption::values('source')]);
    }

    public function monthly(Request $request)
    {
        $me = $this->allowed($request);
        $m = (string) $request->query('month', now()->format('Y-m'));
        $month = CarbonImmutable::createFromFormat('Y-m-d', (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->format('Y-m')).'-01')->startOfDay();
        $people = $me->isRep() ? collect([$me]) : User::active()->visibleTo($me)->whereIn('role', ['rep', 'manager'])->orderBy('name')->get();
        $rows = $this->reports->monthly($people, $month);
        $format = $request->query('export');
        if ($format === 'xlsx') {
            AuditLog::record('report.monthly', $month->format('Y-m'), ['format' => 'xlsx']);
            $header = ['Salesperson', 'Target', 'Achieved', 'Achieved %', 'Gap', 'Orders', 'Average order', 'Pending orders', 'New customers', 'New customer value',
                'Repeat customers', 'Repeat value', 'Last month', 'Growth %', 'Leads added', 'Won', 'Lost', 'Calls', 'Visits', 'Days present', 'Expense claims'];
            $data = $rows->map(fn ($r) => [$r->user->name, $r->target, $r->achieved, $r->percent, $r->gap, $r->orders, round($r->avg_order, 2), $r->pending, $r->new_customers,
                $r->new_value, $r->repeat_customers, $r->repeat_value, $r->last_month, $r->growth, $r->leads_added, $r->won, $r->lost, $r->calls, $r->visits, $r->present, $r->claims])->all();
            $data[] = ['Total', $rows->sum('target'), $rows->sum('achieved'), $rows->sum('target') > 0 ? round($rows->sum('achieved') * 100 / $rows->sum('target')) : null,
                $rows->sum('gap'), $rows->sum('orders'), null, $rows->sum('pending'), $rows->sum('new_customers'), $rows->sum('new_value'), $rows->sum('repeat_customers'),
                $rows->sum('repeat_value'), $rows->sum('last_month'), null, $rows->sum('leads_added'), $rows->sum('won'), $rows->sum('lost'), $rows->sum('calls'), $rows->sum('visits'), null, $rows->sum('claims')];
            $data[] = [];
            $data[] = ['Designed & developed by Solvix Web · Damini Rana · +91 82190 60177'];

            return response()->download(Sheets::write('xlsx', $header, $data), 'monthly-sales-'.$month->format('Y-m').'.xlsx')->deleteFileAfterSend();
        }
        if ($format === 'pdf') {
            AuditLog::record('report.monthly', $month->format('Y-m'), ['format' => 'pdf']);

            return response($this->pdf('reports.monthly-pdf', ['rows' => $rows, 'month' => $month]), 200, ['Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="monthly-sales-'.$month->format('Y-m').'.pdf"']);
        }

        return view('reports.monthly', ['rows' => $rows, 'month' => $month]);
    }

    private function dealFilters(Request $request, User $me): array
    {
        $d = fn ($k, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($k)) ? CarbonImmutable::parse($request->query($k)) : $def;
        $from = $d('from', CarbonImmutable::now()->startOfMonth());
        $to = $d('to', CarbonImmutable::today());
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $owner = (int) $request->query('owner_id');
        $leads = Lead::visibleTo($me)
            ->when($owner && $me->canSeeUser($owner), fn ($q) => $q->where('owner_id', $owner))
            ->when($request->filled('source'), fn ($q) => $q->where('source', (string) $request->query('source')));

        return [$from, $to, $leads];
    }

    private function exportDeals($leads, CarbonImmutable $from, CarbonImmutable $to)
    {
        $range = [$from->startOfDay(), $to->endOfDay()];
        $rows = (clone $leads)->with('owner:id,name')->where(fn ($q) => $q->whereIn('status', Reports::OPEN)->orWhereBetween('won_at', $range)->orWhereBetween('lost_at', $range))
            ->orderBy('status')->get()->map(fn ($l) => [$l->firm_name, $l->city, $l->owner?->name, $l->source, __(Lead::STATUSES[$l->status] ?? $l->status),
                $l->expected_value !== null ? (float) $l->expected_value : null, $l->created_at->format('Y-m-d'), $l->won_at?->format('Y-m-d'), $l->lost_at?->format('Y-m-d'),
                $l->lost_reason, (int) $l->created_at->diffInDays($l->won_at ?? $l->lost_at ?? now())]);
        AuditLog::record('report.deals', $from->toDateString().'..'.$to->toDateString());
        $header = ['Firm', 'City', 'Salesperson', 'Source', 'Stage', 'Deal value', 'Created', 'Won on', 'Lost on', 'Lost reason', 'Days open'];

        return response()->download(Sheets::write('xlsx', $header, $rows->all()), 'deals-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    private function pdf(string $view, array $data): string
    {
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
        $pdf->loadHtml(view($view, $data + ['c' => $c, 'logo' => $logo])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
