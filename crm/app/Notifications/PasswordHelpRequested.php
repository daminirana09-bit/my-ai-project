<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/** A team member forgot their password and no email could be sent: the admin resets it. */
class PasswordHelpRequested extends Notification
{
    public function __construct(public User $user) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'password_help', 'user_id' => $this->user->id, 'name' => $this->user->name];
    }
}
