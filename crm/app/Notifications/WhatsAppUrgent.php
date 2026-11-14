<?php

namespace App\Notifications;

use App\Models\WaConversation;
use Illuminate\Notifications\Notification;

class WhatsAppUrgent extends Notification
{
    public function __construct(public WaConversation $c) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'wa_urgent', 'conversation_id' => $this->c->id, 'name' => $this->c->lead?->firm_name ?? $this->c->contact_name, 'rep' => $this->c->number?->user?->name];
    }
}
