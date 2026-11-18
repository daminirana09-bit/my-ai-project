<?php

namespace App\Notifications;

use App\Models\WaConversation;
use Illuminate\Notifications\Notification;

class ChatAssigned extends Notification
{
    public function __construct(public WaConversation $c, public ?string $by = null, public string $kind = 'chat_assigned') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'conversation_id' => $this->c->id, 'name' => $this->c->lead?->firm_name ?? $this->c->contact_name, 'by' => $this->by];
    }
}
