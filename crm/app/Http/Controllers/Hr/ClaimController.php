<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\ExpenseClaim;
use App\Notifications\HrAlert;
use App\Services\Hr\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** TA/DA and other expenses: DA and km amounts come from the company's rates, never typed in. */
class ClaimController extends Controller
{
    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:-90 days'],
            'kind' => ['required', Rule::in(array_keys(ExpenseClaim::KINDS))],
            'area' => ['required_if:kind,da', 'nullable', Rule::in(array_keys(ExpenseClaim::DA_AREAS))],
            'vehicle' => ['required_if:kind,travel', 'nullable', Rule::in(array_keys(ExpenseClaim::VEHICLES))],
            'km' => ['required_if:kind,travel', 'nullable', 'numeric', 'min:1', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:200000'],
            'from_place' => ['nullable', 'string', 'max:80'],
            'to_place' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:300'],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);
        $co = CompanySetting::current();
        [$detail, $amount, $km] = match ($data['kind']) {
            'da' => [$data['area'], $co->hr('da_'.$data['area']), null],
            'travel' => [$data['vehicle'], round((float) $data['km'] * $co->hr('km_'.$data['vehicle']), 2), (float) $data['km']],
            default => [null, (float) ($data['amount'] ?? 0), null],
        };
        if ($amount <= 0) {
            return back()->withInput()->withErrors(['amount' => __('Enter the amount.')]);
        }
        if ($data['kind'] === 'da' && ExpenseClaim::where('user_id', $me->id)->where('kind', 'da')->whereDate('date', $data['date'])->whereIn('status', ['pending', 'approved', 'paid'])->exists()) {
            return back()->withInput()->withErrors(['date' => __('DA for this date is already claimed.')]);
        }
        $path = $mime = null;
        if ($file = $request->file('receipt')) {
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = ExpenseClaim::RECEIPTS[$ext] ?? null;
            if (! $mime || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'application/pdf'], true)) {
                return back()->withInput()->withErrors(['receipt' => __('Receipt must be a photo (JPG/PNG) or a PDF.')]);
            }
            $path = $file->storeAs('hr/receipts', Str::random(40).'.'.$ext, 'local');
        }
        $c = ExpenseClaim::create(['user_id' => $me->id, 'date' => $data['date'], 'kind' => $data['kind'], 'detail' => $detail, 'km' => $km, 'amount' => $amount,
            'from_place' => $data['from_place'] ?? null, 'to_place' => $data['to_place'] ?? null, 'note' => $data['note'] ?? null,
            'receipt_path' => $path, 'receipt_mime' => $mime, 'status' => 'pending']);
        Notification::send(LeaveService::approversOf($me), new HrAlert('claim_request', ['id' => $c->id, 'name' => $me->name, 'amount' => (float) $amount, 'kind_label' => ExpenseClaim::KINDS[$c->kind]]));
        AuditLog::record('claim.add', "claim:{$c->id}", ['amount' => (float) $amount]);

        return redirect()->route('hr.me', ['tab' => 'claims'])->with('ok', __('Claim of ₹:a sent for approval.', ['a' => number_format($amount, 2)]));
    }

    public function cancel(Request $request, ExpenseClaim $claim)
    {
        abort_unless($claim->user_id === $request->user()->id && $claim->status === 'pending', 403);
        if ($claim->receipt_path) {
            Storage::disk('local')->delete($claim->receipt_path);
        }
        $claim->delete();

        return back()->with('ok', __('Claim removed.'));
    }

    public function receipt(Request $request, ExpenseClaim $claim)
    {
        $me = $request->user();
        abort_unless($claim->receipt_path && ($claim->user_id === $me->id || LeaveService::canApprove($me, $claim->user)), 404);
        abort_unless(Storage::disk('local')->exists($claim->receipt_path), 404);

        return response()->file(Storage::disk('local')->path($claim->receipt_path), ['Content-Type' => $claim->receipt_mime,
            'Content-Disposition' => 'inline; filename="receipt-'.$claim->id.'.'.pathinfo($claim->receipt_path, PATHINFO_EXTENSION).'"', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function decide(Request $request, ExpenseClaim $claim)
    {
        $me = $request->user();
        abort_unless(LeaveService::canApprove($me, $claim->user), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:300']]);
        if ($claim->status !== 'pending') {
            return back()->withErrors(['claim' => __('This claim was already decided.')]);
        }
        $claim->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_by' => $me->id, 'decided_at' => now(), 'decision_note' => $data['note'] ?? null]);
        $claim->user->notify(new HrAlert('claim_decided', ['approved' => $claim->status === 'approved', 'amount' => (float) $claim->amount, 'by' => $me->name]));
        AuditLog::record('claim.'.$data['decision'], "claim:{$claim->id}");

        return back()->with('ok', $claim->status === 'approved' ? __('Claim approved. It is paid with the next salary.') : __('Claim rejected.'));
    }
}
