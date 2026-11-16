<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use App\Notifications\PasswordHelpRequested;
use App\Notifications\PasswordResetLink;
use App\Services\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/** Forgot password, reset link, admin reset with "change at next login", and the email settings. */
class PasswordResetTest extends CrmTestCase
{
    private const SAME = 'If this email has a login, a link to set a new password is on its way.';

    private function forgot(string $email)
    {
        return $this->post('/forgot-password', ['email' => $email]);
    }

    public function test_login_page_has_forgot_link_and_forgot_page_opens(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'), false);
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot password?');
        $this->actingAs($this->repA)->get('/forgot-password')->assertRedirect(); // already logged in
    }

    public function test_reset_link_by_email_works_once_and_logs_out_elsewhere(): void
    {
        Notification::fake();
        $this->forgot(strtoupper($this->repA->email))->assertSessionHas('ok', fn ($m) => str_starts_with($m, self::SAME));
        $token = null;
        Notification::assertSentTo($this->repA, PasswordResetLink::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->repA->id, 'payload' => '', 'last_activity' => time()]);

        $this->get('/reset-password/'.$token.'?email='.urlencode($this->repA->email))->assertOk()->assertSee($this->repA->email);
        $this->post('/reset-password', ['token' => $token, 'email' => $this->repA->email, 'password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => $this->repA->email, 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'])
            ->assertRedirect('/login')->assertSessionHas('ok');
        $this->assertTrue(Hash::check('NewPass12345', $this->repA->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->repA->id)->count(), 'logged out on every other device');

        // The same link a second time does nothing
        $this->post('/reset-password', ['token' => $token, 'email' => $this->repA->email, 'password' => 'Another12345', 'password_confirmation' => 'Another12345'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NewPass12345', $this->repA->fresh()->password));
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'NewPass12345'])->assertRedirect('/');
    }

    public function test_link_expires_and_cannot_be_used_for_another_person(): void
    {
        Notification::fake();
        $this->forgot($this->repA->email);
        $token = null;
        Notification::assertSentTo($this->repA, PasswordResetLink::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->post('/reset-password', ['token' => $token, 'email' => $this->repB->email, 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'])->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => $this->repA->email, 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'])->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('NewPass12345', $this->repA->fresh()->password));
    }

    public function test_unknown_and_inactive_emails_get_the_same_answer_and_nothing_is_sent(): void
    {
        Notification::fake();
        $this->forgot('nobody@example.com')->assertSessionHas('ok', fn ($m) => str_starts_with($m, self::SAME));
        $this->repB->update(['active' => false]);
        $this->forgot($this->repB->email)->assertSessionHas('ok', fn ($m) => str_starts_with($m, self::SAME));
        Notification::assertNothingSent();
    }

    public function test_without_email_the_admin_is_told_and_asking_again_and_again_is_limited(): void
    {
        config(['mail.default' => 'log']);
        Notification::fake();
        $this->forgot($this->repA->email)->assertSessionHas('ok');
        Notification::assertSentTo($this->admin, PasswordHelpRequested::class);
        Notification::assertNotSentTo($this->repA, PasswordResetLink::class);
        Notification::assertNotSentTo($this->manager, PasswordHelpRequested::class);
        $this->forgot($this->repA->email);
        $this->forgot($this->repA->email);
        $this->forgot($this->repA->email); // 4th time within 15 minutes
        Notification::assertSentToTimes($this->admin, PasswordHelpRequested::class, 3);
    }

    public function test_admin_sees_the_alert_and_resets_with_change_at_next_login(): void
    {
        config(['mail.default' => 'log']);
        $this->forgot($this->repA->email);
        $this->actingAs($this->admin)->get('/notifications')->assertSee('Amit forgot the password')->assertSee(route('users.edit', $this->repA->id), false);

        // Admin types a temporary password: Amit must choose his own before anything else
        DB::table('sessions')->insert(['id' => 'amit-phone', 'user_id' => $this->repA->id, 'payload' => '', 'last_activity' => time()]);
        $this->put("/users/{$this->repA->id}", ['name' => 'Amit', 'email' => $this->repA->email, 'role' => 'rep', 'locale' => 'en', 'active' => 1,
            'manager_id' => $this->manager->id, 'password' => 'TempPass2026', 'must_change_password' => 1])->assertRedirect('/users');
        $this->assertTrue($this->repA->fresh()->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->repA->id)->count());

        auth()->logout();
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'TempPass2026']);
        $this->get('/')->assertRedirect('/profile');
        $this->get('/leads')->assertRedirect('/profile');
        $this->get('/profile')->assertOk();
        $this->post('/profile/password', ['current_password' => 'TempPass2026', 'password' => 'MyOwnPass2026', 'password_confirmation' => 'MyOwnPass2026'])->assertSessionHas('ok');
        $this->assertFalse($this->repA->fresh()->must_change_password);
        $this->get('/leads')->assertOk();

        // New people also choose their own password first, when the box is ticked
        $this->actingAs($this->admin)->post('/users', ['name' => 'Neha', 'email' => 'neha@test.in', 'role' => 'rep', 'locale' => 'hi', 'password' => 'Welcome2026x', 'must_change_password' => 1]);
        $this->assertTrue(User::where('email', 'neha@test.in')->sole()->must_change_password);
        // Changing your own details never locks you
        $this->put("/users/{$this->admin->id}", ['name' => 'Owner', 'email' => $this->admin->email, 'role' => 'admin', 'locale' => 'en', 'password' => 'AdminPass2026', 'must_change_password' => 1]);
        $this->assertFalse($this->admin->fresh()->must_change_password);
    }

    public function test_admin_emails_a_reset_link(): void
    {
        Notification::fake();
        $this->actingAs($this->repB)->post("/users/{$this->repA->id}/send-reset")->assertForbidden();
        $this->actingAs($this->admin)->post("/users/{$this->repA->id}/send-reset")->assertSessionHas('ok');
        Notification::assertSentTo($this->repA, PasswordResetLink::class);
        config(['mail.default' => 'log']);
        $this->post("/users/{$this->repB->id}/send-reset")->assertSessionHasErrors('password');
    }

    public function test_email_settings_are_kept_secret_and_test_mail_is_sent(): void
    {
        $this->actingAs($this->repA)->post('/settings/mail', ['mail_host' => 'smtp.hostinger.com'])->assertForbidden();
        $this->actingAs($this->admin)->post('/settings/mail', ['mail_host' => 'https://smtp.x.com', 'mail_port' => 465])->assertSessionHasErrors('mail_host');
        $this->post('/settings/mail', ['mail_host' => '127.0.0.1', 'mail_port' => 465])->assertSessionHasErrors('mail_host');
        $this->post('/settings/mail', ['mail_host' => '93.184.216.34', 'mail_port' => 25])->assertSessionHasErrors('mail_port');
        $this->post('/settings/mail', ['mail_host' => '93.184.216.34', 'mail_port' => 465, 'mail_username' => 'noreply@dermain.test', 'mail_password' => 'Secret-Mail-1'])->assertSessionHas('ok');
        $this->assertStringNotContainsString('Secret-Mail-1', (string) DB::table('company_settings')->value('mail_config'));
        $this->get('/settings')->assertOk()->assertDontSee('Secret-Mail-1')->assertSee('Saved. Leave empty to keep.');
        $this->post('/settings/mail', ['mail_host' => '93.184.216.34', 'mail_port' => 587, 'mail_username' => 'noreply@dermain.test', 'mail_password' => '']);
        CompanySetting::forget();
        $this->assertSame('Secret-Mail-1', CompanySetting::current()->mail('password'), 'empty box keeps the saved password');

        Mail::fake();
        $this->assertTrue(MailSettings::apply());
        $this->assertSame(['smtp', '93.184.216.34', 587, 'smtp', 'noreply@dermain.test'], [config('mail.default'), config('mail.mailers.smtp.host'),
            config('mail.mailers.smtp.port'), config('mail.mailers.smtp.scheme'), config('mail.from.address')]);
        $this->post('/settings/mail/test')->assertSessionHas('ok');
    }

    public function test_reset_email_says_who_and_comes_in_the_persons_language(): void
    {
        $this->repA->update(['locale' => 'hi']);
        $mail = (new PasswordResetLink('tok123'))->toMail($this->repA->fresh());
        $this->assertStringContainsString('Test Pharma Pvt Ltd', $mail->subject);
        $this->assertStringContainsString('/reset-password/tok123', $mail->actionUrl);
        $this->assertSame('hi', $this->repA->fresh()->preferredLocale());
    }

    public function test_locked_out_admin_can_be_reset_from_the_server(): void
    {
        $this->artisan('crm:reset-password', ['email' => 'nobody@x.test'])->assertExitCode(1);
        $this->artisan('crm:reset-password', ['email' => strtoupper($this->admin->email)])->expectsOutputToContain('Temporary password for')->assertExitCode(0);
        $this->assertTrue($this->admin->fresh()->must_change_password);
        $this->assertFalse(Hash::check('password', $this->admin->fresh()->password));
    }
}
