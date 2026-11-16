<?php

namespace App\Notifications;

use App\Models\CompanySetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The "set a new password" email. The link works once and for 60 minutes. */
class PasswordResetLink extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = (string) CompanySetting::current()?->company_name;
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('Set a new password · :company', ['company' => $company]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Someone asked to set a new password for your :company sales CRM login.', ['company' => $company]))
            ->action(__('Set a new password'), $url)
            ->line(__('The link works once, for :n minutes.', ['n' => $minutes]))
            ->line(__('If you did not ask for this, ignore this email: your password stays the same.'))
            ->salutation($company);
    }
}
