<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A short daily note (overdue follow-ups, stale leads) shown under Alerts. */
class Digest extends Notification
{
    public function __construct(public string $kind, public array $data) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind] + $this->data;
    }
}
