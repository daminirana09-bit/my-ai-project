<?php

namespace App\Notifications;

use App\Models\LeadClash;
use Illuminate\Notifications\Notification;

/** Shown in the bell menu of admins and managers. Stays inside the CRM database. */
class LeadClashDetected extends Notification
{
    public function __construct(public LeadClash $clash) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $c = $this->clash->loadMissing('lead', 'existingOwner', 'otherUser');

        return [
            'kind' => 'lead_clash',
            'clash_id' => $c->id,
            'lead_id' => $c->lead_id,
            'firm' => $c->lead?->firm_name,
            'owner' => $c->existingOwner?->name,
            'other' => $c->otherUser?->name,
            'reason' => $c->reason,
        ];
    }
}
