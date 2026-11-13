<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LeadActivity;
use App\Models\LeadClash;
use App\Models\User;
use Illuminate\Http\Request;

/** Admin and managers decide who keeps a client that two salespeople are handling. */
class ClashController extends Controller
{
    private function scoped(User $me)
    {
        $ids = $me->visibleUserIds();

        return LeadClash::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('existing_owner_id', $ids)->orWhereIn('other_user_id', $ids)));
    }

    public function index(Request $request)
    {
        $me = $request->user();

        return view('clashes.index', [
            'open' => $this->scoped($me)->where('status', 'open')->with(['lead', 'existingOwner', 'otherUser'])->latest()->get(),
            'resolved' => $this->scoped($me)->where('status', 'resolved')->with(['lead', 'assignedTo', 'resolver'])->latest('resolved_at')->limit(20)->get(),
            'owners' => User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function resolve(Request $request, LeadClash $clash)
    {
        $me = $request->user();
        abort_unless($this->scoped($me)->whereKey($clash->id)->exists() && $clash->status === 'open', 404);
        $to = User::active()->visibleTo($me)->findOrFail((int) $request->input('assigned_to'));
        $lead = $clash->lead;
        $from = $lead->owner?->name;

        $lead->update(['owner_id' => $to->id]);
        LeadClash::where('lead_id', $lead->id)->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_by' => $me->id, 'assigned_to' => $to->id, 'resolved_at' => now()]);
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $me->id, 'type' => 'assign', 'note' => __('Clash resolved').': '.($from ?: '—').' → '.$to->name, 'occurred_at' => now()]);
        AuditLog::record('clash.resolve', "lead:{$lead->id}", ['to' => $to->id]);

        return back()->with('ok', __(':firm is now with :name.', ['firm' => $lead->firm_name, 'name' => $to->name]));
    }
}
