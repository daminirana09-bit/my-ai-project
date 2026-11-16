<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Mail;

/**
 * Outgoing email (only for password-reset links and test mails). The company enters its own
 * mailbox in Settings (Hostinger email, Gmail with an app password…); nothing goes through us.
 */
class MailSettings
{
    /** Uses the company's SMTP settings if saved. True when email can actually be sent. */
    public static function apply(): bool
    {
        $c = CompanySetting::current();
        if ($c && $c->mail('host') && $c->mail('username') && $c->mail('password')) {
            $port = (int) $c->mail('port', 465);
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $c->mail('host'),
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => $c->mail('username'),
                'mail.mailers.smtp.password' => $c->mail('password'),
                'mail.mailers.smtp.timeout' => 20,
                'mail.from.address' => $c->mail('from', $c->mail('username')),
                'mail.from.name' => (string) $c->company_name,
            ]);
            Mail::purge('smtp');

            return true;
        }

        // Set up in .env instead (or the test mailer).
        return ! in_array(config('mail.default'), ['log', null, ''], true);
    }
}
