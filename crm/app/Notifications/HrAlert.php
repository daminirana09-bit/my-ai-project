<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app HR alerts: leave or claim asked for, approved or rejected, payslip ready. */
class HrAlert extends Notification
{
    public function __construct(public string $kind, public array $data = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind] + $this->data;
    }
}
