<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use App\Services\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Monthly target per salesperson, sales done, and what is still pending. */
class TargetController extends Controller
{
    private function month(Request $request): string
    {
        $m = (string) $request->query('month', now()->format('Y-m'));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->format('Y-m');
    }

    public function index(Request $request, SalesReport $report)
    {
        $me = $request->user();
        $month = $this->month($request);
        $people = $me->isRep() ? collect([$me]) : User::active()->visibleTo($me)->where('role', '!=', 'admin')->orderBy('name')->get();
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        return view('targets.index', [
            'month' => $month,
            'rows' => $report->rows($people, $month),
            'sales' => Sale::whereIn('user_id', $people->pluck('id'))->whereBetween('sale_date', [$start, $end])
                ->with(['user:id,name', 'lead:id,firm_name'])->latest('sale_date')->latest('id')->limit(200)->get(),
            'myLeads' => Lead::visibleTo($me)->whereNotIn('status', ['lost'])->orderBy('firm_name')->limit(500)->get(['id', 'firm_name']),
            'people' => $people,
        ]);
    }

    public function saveTargets(Request $request)
    {
        $me = $request->user();
        $month = $this->month($request);
        $data = $request->validate(['targets' => ['array'], 'targets.*' => ['nullable', 'numeric', 'min:0']]);
        foreach ($data['targets'] ?? [] as $userId => $amount) {
            if (! $me->canSeeUser($userId)) {
                continue;
            }
            if ($amount === null || $amount === '') {
                Target::where('user_id', $userId)->where('month', $month)->delete();
            } else {
                Target::updateOrCreate(['user_id' => $userId, 'month' => $month], ['amount' => $amount]);
            }
        }
        AuditLog::record('targets.save', $month);

        return back()->with('ok', __('Targets saved.'));
    }

    public function storeSale(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'invoice_no' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1'],
            'sale_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(Sale::STATUSES))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        // A rep records only their own sales, and they stay "pending" until a manager confirms.
        $data['user_id'] = $me->isRep() ? $me->id : User::visibleTo($me)->findOrFail((int) ($data['user_id'] ?? $me->id))->id;
        if ($me->isRep()) {
            $data['status'] = 'pending';
        }
        if (! empty($data['lead_id'])) {
            abort_unless(Lead::visibleTo($me)->whereKey($data['lead_id'])->exists(), 404);
        }
        Sale::create($data);
        AuditLog::record('sale.create', null, ['amount' => $data['amount']]);

        return back()->with('ok', __('Sale saved.'));
    }

    public function updateSale(Request $request, Sale $sale)
    {
        $me = $request->user();
        abort_unless($me->canManage() && $me->canSeeUser($sale->user_id), 404);
        $sale->update($request->validate(['status' => ['required', Rule::in(array_keys(Sale::STATUSES))]]));
        AuditLog::record('sale.status', "sale:{$sale->id}", ['status' => $sale->status]);

        return back()->with('ok', __('Saved.'));
    }
}
