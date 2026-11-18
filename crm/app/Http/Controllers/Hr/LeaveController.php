<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Notifications\HrAlert;
use App\Services\Hr\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** Asking for leave, cancelling it, and approving it. */
class LeaveController extends Controller
{
    public function __construct(private LeaveService $leaves) {}

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('active', true)],
            'from_date' => ['required', 'date', 'after_or_equal:-60 days', 'before:+400 days'],
            'to_date' => ['required', 'date'],
            'half_day' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $type = LeaveType::findOrFail($data['leave_type_id']);
        $from = CarbonImmutable::parse($data['from_date']);
        $to = CarbonImmutable::parse($data['to_date']);
        $half = $request->boolean('half_day');
        if ($why = $this->leaves->problem($me, $type, $from, $to, $half)) {
            return back()->withInput()->withErrors(['from_date' => $why]);
        }
        $r = LeaveRequest::create(['user_id' => $me->id, 'leave_type_id' => $type->id, 'from_date' => $from->toDateString(), 'to_date' => $to->toDateString(),
            'half_day' => $half, 'days' => $this->leaves->countDays($from, $to, $half), 'reason' => $data['reason'], 'status' => 'pending']);
        Notification::send(LeaveService::approversOf($me), new HrAlert('leave_request', ['id' => $r->id, 'name' => $me->name, 'type' => $type->name,
            'from' => $from->format('d M'), 'to' => $to->format('d M'), 'days' => (float) $r->days]));
        AuditLog::record('leave.apply', "leave:{$r->id}", ['days' => (float) $r->days]);

        return redirect()->route('hr.me')->with('ok', __('Leave asked for :n days. Your manager has been told.', ['n' => rtrim(rtrim(number_format((float) $r->days, 1), '0'), '.')]));
    }

    /** The person takes back a request still waiting; an approver can also take back an approved one. */
    public function cancel(Request $request, LeaveRequest $leave)
    {
        $me = $request->user();
        $own = $leave->user_id === $me->id && $leave->status === 'pending';
        $approver = $leave->status === 'approved' && LeaveService::canApprove($me, $leave->user);
        abort_unless($own || $approver, 403);
        if ($leave->status === 'approved') {
            $this->leaves->undo($leave);
        }
        $leave->update(['status' => 'cancelled', 'decided_by' => $me->id, 'decided_at' => now()]);
        AuditLog::record('leave.cancel', "leave:{$leave->id}");

        return back()->with('ok', __('Leave cancelled.'));
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $me = $request->user();
        abort_unless(LeaveService::canApprove($me, $leave->user), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:300']]);
        if ($leave->status !== 'pending') {
            return back()->withErrors(['leave' => __('This request was already decided.')]);
        }
        if ($data['decision'] === 'approve') {
            $this->leaves->approve($leave, $me, $data['note'] ?? null);
        } else {
            $leave->update(['status' => 'rejected', 'decided_by' => $me->id, 'decided_at' => now(), 'decision_note' => $data['note'] ?? null]);
        }
        $leave->user->notify(new HrAlert('leave_decided', ['approved' => $data['decision'] === 'approve', 'type' => $leave->type->name,
            'from' => $leave->from_date->format('d M'), 'to' => $leave->to_date->format('d M'), 'by' => $me->name]));
        AuditLog::record('leave.'.$data['decision'], "leave:{$leave->id}");

        return back()->with('ok', $data['decision'] === 'approve' ? __('Leave approved and marked in attendance.') : __('Leave rejected.'));
    }
}
