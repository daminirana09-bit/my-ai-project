<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadClash;
use App\Models\User;
use App\Notifications\LeadClashDetected;
use Illuminate\Support\Facades\Notification;

/**
 * Finds the same client handled by two salespeople and tells admins and managers,
 * so they can decide who keeps the lead.
 */
class ClashDetector
{
    /** An existing lead for the same client: same phone number or same GSTIN. */
    public function findExisting(?string $phone, ?string $gstin, ?int $ignoreLeadId = null): ?Lead
    {
        $hash = Lead::hashPhone($phone);
        $gstin = $gstin ? strtoupper(trim($gstin)) : null;
        if (! $hash && ! $gstin) {
            return null;
        }

        return Lead::query()
            ->when($ignoreLeadId, fn ($q) => $q->where('id', '!=', $ignoreLeadId))
            ->where(function ($q) use ($hash, $gstin) {
                if ($hash) {
                    $q->orWhere('phone_hash', $hash);
                }
                if ($gstin) {
                    $q->orWhere('gstin', $gstin);
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Record that $other is working a client that $lead's owner already has.
     * Only one open clash per lead and person; repeat attempts update its detail.
     */
    public function report(Lead $lead, User $other, string $reason, ?string $detail = null): LeadClash
    {
        $clash = LeadClash::firstOrNew([
            'lead_id' => $lead->id,
            'other_user_id' => $other->id,
            'status' => 'open',
        ]);
        $isNew = ! $clash->exists;
        $clash->fill([
            'existing_owner_id' => $lead->owner_id,
            'reason' => $reason,
            'detail' => $detail,
        ])->save();

        if ($isNew) {
            Notification::send($this->recipients($lead->owner, $other), new LeadClashDetected($clash));
        }

        return $clash;
    }

    /** All admins, plus the managers of both salespeople. */
    private function recipients(?User $owner, User $other)
    {
        $ids = User::active()->where('role', 'admin')->pluck('id')
            ->merge(array_filter([$owner?->manager_id, $other->manager_id]))
            ->unique();

        return User::active()->whereIn('id', $ids)->get()->filter(fn (User $u) => $u->canManage());
    }
}
