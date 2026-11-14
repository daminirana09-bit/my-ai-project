<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Notification;

/** A lead came in from IndiaMART, Facebook, the website… (or the same client enquired again). */
class NewLeadArrived extends Notification
{
    public function __construct(public Lead $lead, public string $source, public bool $again = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'new_lead', 'lead_id' => $this->lead->id, 'firm' => $this->lead->firm_name, 'source' => $this->source, 'again' => $this->again];
    }
}
