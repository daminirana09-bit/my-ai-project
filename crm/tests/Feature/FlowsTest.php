<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadClash;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\XLSX\Reader;
use PragmaRX\Google2FA\Google2FA;

/** End-to-end flows through the real routes. */
class FlowsTest extends CrmTestCase
{
    public function test_login_logout_and_wrong_password(): void
    {
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => strtoupper($this->repA->email), 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->repA);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->repA->update(['active' => false]);
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_passwords_lock_that_email_only(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $this->repA->email, 'password' => 'bad']);
        }
        // Even the right password is refused for a while for this email...
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        // ...but colleagues on the same office IP can still log in.
        $this->post('/login', ['email' => $this->repB->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->repB);
    }

    public function test_whole_office_can_log_in_at_the_same_time(): void
    {
        $people = User::factory()->count(20)->create(['role' => 'rep']);
        foreach ($people as $u) {
            $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/');
            $this->post('/logout');
        }
    }

    public function test_two_step_login_full_flow(): void
    {
        $g = new Google2FA;
        $this->actingAs($this->repA)->post('/profile/2fa/start')->assertRedirect();
        $secret = session('2fa:pending');
        $this->assertNotEmpty($secret);
        $this->actingAs($this->repA)->post('/profile/2fa/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($this->repA)->post('/profile/2fa/confirm', ['code' => $g->getCurrentOtp($secret)])->assertSessionHasNoErrors();
        $this->assertNotNull($this->repA->fresh()->two_factor_secret);
        $this->assertStringNotContainsString($secret, (string) \DB::table('users')->where('id', $this->repA->id)->value('two_factor_secret'));

        $this->post('/logout');
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'password'])->assertRedirect('/login/otp');
        $this->assertGuest();
        $this->get('/')->assertRedirect('/login');
        $this->post('/login/otp', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post('/login/otp', ['code' => $g->getCurrentOtp($secret)])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->repA->fresh());
    }

    public function test_admin_resets_lost_two_step(): void
    {
        $this->repA->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP'])->save();
        $this->actingAs($this->admin)->put("/users/{$this->repA->id}", $this->userPayload($this->repA, ['reset_2fa' => 1]))->assertRedirect('/users');
        $this->assertNull($this->repA->fresh()->two_factor_secret);
    }

    private function userPayload(User $u, array $extra = []): array
    {
        return array_merge(['name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'locale' => 'en', 'active' => 1, 'manager_id' => $u->manager_id], $extra);
    }

    public function test_admin_adds_user_and_new_user_logs_in(): void
    {
        $this->actingAs($this->admin)->post('/users', ['name' => 'New Rep', 'email' => 'NEW@x.in', 'role' => 'rep', 'locale' => 'hi', 'active' => 1, 'password' => 'abc'])->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/users', ['name' => 'New Rep', 'email' => 'NEW@x.in', 'role' => 'rep', 'locale' => 'hi', 'active' => 1, 'password' => 'goodpass123', 'manager_id' => $this->manager->id])->assertRedirect('/users');
        $this->actingAs($this->admin)->post('/users', ['name' => 'Dup', 'email' => 'new@x.in', 'role' => 'rep', 'locale' => 'en', 'password' => 'goodpass123'])->assertSessionHasErrors('email');
        $this->post('/logout');
        $this->post('/login', ['email' => 'new@x.in', 'password' => 'goodpass123'])->assertRedirect('/');
        $this->get('/leads')->assertSee('लीड्स');
    }

    public function test_person_leaving_loses_access_and_leads_move(): void
    {
        $l1 = Lead::create(['firm_name' => 'L1', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $l2 = Lead::create(['firm_name' => 'L2', 'status' => 'won', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->admin)->put("/users/{$this->repA->id}", $this->userPayload($this->repA, ['active' => 0, 'move_leads_to' => $this->repB->id]))->assertRedirect('/users');
        $this->assertFalse($this->repA->fresh()->active);
        $this->assertSame($this->repB->id, $l1->fresh()->owner_id);
        $this->assertSame($this->repB->id, $l2->fresh()->owner_id);
        $this->actingAs($this->repA->fresh())->get('/clashes')->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $this->repA->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin)->put("/users/{$this->admin->id}", $this->userPayload($this->admin, ['role' => 'rep', 'active' => 0]));
        $this->assertSame('admin', $this->admin->fresh()->role);
        $this->assertTrue($this->admin->fresh()->active);
    }

    public function test_settings_save_details_and_logo(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 80, 80);
        $this->actingAs($this->admin)->post('/settings', ['company_name' => 'Renamed Pharma', 'gstin' => '03ABCDE1234F1Z5', 'logo' => $logo])->assertSessionHasNoErrors();
        CompanySetting::forget();
        $this->assertSame('Renamed Pharma', CompanySetting::current()->company_name);
        $this->actingAs($this->repA)->get('/branding/logo')->assertOk();
        $this->actingAs($this->repA)->get('/')->assertSee('Renamed Pharma');
        $this->actingAs($this->manager)->post('/settings', ['company_name' => 'Hijack'])->assertForbidden();
    }

    public function test_office_hours_timings_and_weekly_off(): void
    {
        $this->actingAs($this->admin)->post('/office-hours', [
            'office_start' => '10:00', 'office_end' => '19:00', 'lunch_start' => '14:00', 'lunch_end' => '14:45',
            'late_grace_minutes' => 10, 'full_day_hours' => 7.5, 'half_day_hours' => 3.5,
        ])->assertSessionHasNoErrors();
        CompanySetting::forget();
        $c = CompanySetting::current();
        $this->assertSame(450, $c->full_day_minutes);
        $this->assertSame('', $c->weekly_off); // no weekly off ticked
        $this->assertSame('10:00', substr($c->office_start, 0, 5));
        $this->actingAs($this->admin)->post('/office-hours', ['office_start' => '10:00', 'office_end' => '09:00', 'lunch_start' => '14:00', 'lunch_end' => '14:45', 'late_grace_minutes' => 0, 'full_day_hours' => 4, 'half_day_hours' => 6])
            ->assertSessionHasErrors(['office_end', 'half_day_hours']);
        $this->actingAs($this->manager)->get('/office-hours')->assertForbidden();
    }

    public function test_reps_can_export_only_their_own_when_allowed(): void
    {
        CompanySetting::current()->update(['reps_can_export_own_leads' => true]);
        CompanySetting::forget();
        Lead::create(['firm_name' => 'Mine Firm', 'phone' => '9000000001', 'status' => 'new', 'owner_id' => $this->repA->id]);
        Lead::create(['firm_name' => 'Other Firm', 'phone' => '9000000002', 'status' => 'new', 'owner_id' => $this->repB->id]);
        $res = $this->actingAs($this->repA)->get('/leads/export?format=xlsx')->assertOk();
        $path = $res->baseResponse->getFile()->getPathname();
        $r = new Reader;
        $r->open($path);
        $rows = [];
        foreach ($r->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $r->close();
        $this->assertSame('Firm name', $rows[0][0]);
        $this->assertCount(2, $rows);
        $this->assertSame('Mine Firm', $rows[1][0]);
        $this->assertSame('9000000001', (string) $rows[1][2]);
    }

    public function test_lead_edit_activity_and_duplicate_phone(): void
    {
        $a = Lead::create(['firm_name' => 'A', 'phone' => '9000000011', 'status' => 'new', 'owner_id' => $this->repA->id]);
        Lead::create(['firm_name' => 'B', 'phone' => '9000000012', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->repA)->put("/leads/{$a->id}", ['firm_name' => 'A', 'phone' => '9000000012', 'status' => 'new'])->assertSessionHasErrors('phone');
        $this->actingAs($this->repA)->put("/leads/{$a->id}", ['firm_name' => 'A Renamed', 'phone' => '9000000011', 'status' => 'interested'])->assertRedirect("/leads/{$a->id}");
        $this->actingAs($this->repA)->post("/leads/{$a->id}/activity", ['type' => 'call', 'outcome' => 'connected', 'note' => 'Spoke', 'status' => 'quote_sent', 'next_follow_up_on' => now()->addDays(2)->toDateString()])->assertRedirect();
        $a->refresh();
        $this->assertSame('quote_sent', $a->status);
        $this->assertNotNull($a->last_contact_at);
        $this->assertSame(now()->addDays(2)->toDateString(), $a->next_follow_up_on->toDateString());
        $this->assertSame(3, $a->activities()->count()); // status (edit) + call + status
        $this->actingAs($this->repA)->get("/leads/{$a->id}")->assertSee('Spoke')->assertSee('A Renamed');
        $this->actingAs($this->repA)->post("/leads/{$a->id}/activity", ['type' => 'hack'])->assertSessionHasErrors('type');
    }

    public function test_changing_phone_to_other_reps_client_is_blocked_and_reported(): void
    {
        $theirs = Lead::create(['firm_name' => 'Theirs', 'phone' => '9000000077', 'status' => 'new', 'owner_id' => $this->repB->id]);
        $mine = Lead::create(['firm_name' => 'Mine', 'phone' => '9000000078', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->repA)->put("/leads/{$mine->id}", ['firm_name' => 'Mine', 'phone' => '9000000077', 'status' => 'new'])->assertSessionHasErrors('phone');
        $this->assertSame('9000000078', $mine->fresh()->phone);
        $this->assertDatabaseHas('lead_clashes', ['lead_id' => $theirs->id, 'other_user_id' => $this->repA->id]);
    }

    public function test_assign_rules(): void
    {
        $lead = Lead::create(['firm_name' => 'A', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->repA)->post("/leads/{$lead->id}/assign", ['owner_id' => $this->repB->id])->assertForbidden();
        $this->actingAs($this->manager)->post("/leads/{$lead->id}/assign", ['owner_id' => $this->otherTeamRep->id])->assertNotFound();
        $this->actingAs($this->manager)->post("/leads/{$lead->id}/assign", ['owner_id' => $this->repB->id])->assertRedirect();
        $this->assertSame($this->repB->id, $lead->fresh()->owner_id);
        $this->assertTrue(AuditLog::where('action', 'lead.assign')->exists());
    }

    public function test_manager_creates_lead_for_team_member_only(): void
    {
        $this->actingAs($this->manager)->post('/leads', ['firm_name' => 'For B', 'status' => 'new', 'owner_id' => $this->repB->id])->assertRedirect();
        $this->assertSame($this->repB->id, Lead::where('firm_name', 'For B')->value('owner_id'));
        $this->actingAs($this->manager)->post('/leads', ['firm_name' => 'Outside', 'status' => 'new', 'owner_id' => $this->otherTeamRep->id])->assertNotFound();
        // a rep cannot choose an owner: it is always themselves
        $this->actingAs($this->repA)->post('/leads', ['firm_name' => 'Rep Lead', 'status' => 'new', 'owner_id' => $this->repB->id]);
        $this->assertSame($this->repA->id, Lead::where('firm_name', 'Rep Lead')->value('owner_id'));
    }

    public function test_unassigned_client_goes_to_whoever_adds_it(): void
    {
        $lead = Lead::create(['firm_name' => 'Orphan', 'phone' => '9000000099', 'status' => 'new', 'owner_id' => null]);
        $this->actingAs($this->repA)->post('/leads', ['firm_name' => 'Orphan Again', 'phone' => '9000000099', 'status' => 'new'])->assertRedirect("/leads/{$lead->id}");
        $this->assertSame($this->repA->id, $lead->fresh()->owner_id);
        $this->assertSame(0, LeadClash::count());
    }

    public function test_clash_alert_shows_in_alerts_and_repeat_does_not_spam(): void
    {
        $lead = Lead::create(['firm_name' => 'Shree', 'phone' => '9000000050', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->repB)->post('/leads', ['firm_name' => 'S', 'phone' => '9000000050', 'status' => 'new']);
        $this->actingAs($this->repB)->post('/leads', ['firm_name' => 'S', 'phone' => '9000000050', 'status' => 'new']);
        $this->assertSame(1, LeadClash::count());
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->actingAs($this->admin)->get('/notifications')->assertSee('Shree')->assertSee('Bhavna');
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->actingAs($this->repA)->get("/leads/{$lead->id}")->assertDontSee('Bhavna');
    }

    public function test_sales_status_rules(): void
    {
        $sale = Sale::create(['user_id' => $this->otherTeamRep->id, 'amount' => 100, 'sale_date' => now(), 'status' => 'pending']);
        $this->actingAs($this->manager)->patch("/sales/{$sale->id}", ['status' => 'confirmed'])->assertNotFound();
        $mine = Sale::create(['user_id' => $this->repA->id, 'amount' => 100, 'sale_date' => now(), 'status' => 'pending']);
        $this->actingAs($this->manager)->patch("/sales/{$mine->id}", ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $mine->fresh()->status);
        $this->actingAs($this->repA)->patch("/sales/{$mine->id}", ['status' => 'pending'])->assertForbidden();
    }

    public function test_bad_import_files_are_rejected_cleanly(): void
    {
        $this->actingAs($this->repA)->post('/leads/import', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
        $path = tempnam(sys_get_temp_dir(), 'b').'.csv';
        file_put_contents($path, "Colour,Size\nred,9\n");
        $this->actingAs($this->repA)->post('/leads/import', ['file' => new UploadedFile($path, 'b.csv', 'text/csv', null, true)])
            ->assertSessionHas('import', fn ($r) => $r['created'] === 0 && count($r['errors']) === 1);
        $this->assertSame(0, Lead::count());
    }

    public function test_every_page_renders_in_all_three_languages_for_every_role(): void
    {
        $lead = Lead::create(['firm_name' => 'Page Test', 'status' => 'new', 'owner_id' => $this->repA->id]);
        Sale::create(['user_id' => $this->repA->id, 'amount' => 100, 'sale_date' => now(), 'status' => 'pending']);
        $common = ['/', '/leads', '/leads/new', '/leads/import', "/leads/{$lead->id}", "/leads/{$lead->id}/edit", '/targets', '/attendance/me', '/profile', '/notifications', '/leads?status=won&q=x&due=1'];
        $manage = ['/clashes', '/attendance/today', '/attendance/register', "/attendance/people/{$this->repA->id}", '/targets?month=2026-01', '/attendance/register?month=2025-02'];
        $admin = ['/users', '/users/new', "/users/{$this->repA->id}/edit", '/settings', '/audit'];
        foreach (['en', 'hing', 'hi'] as $loc) {
            foreach ([[$this->repA, $common], [$this->manager, array_merge($common, $manage)], [$this->admin, array_merge($common, $manage, $admin)]] as [$u, $urls]) {
                $u->update(['locale' => $loc]);
                foreach ($urls as $url) {
                    $this->actingAs($u->fresh())->get($url)->assertOk();
                }
            }
        }
    }

    public function test_bad_month_and_unknown_ids_do_not_crash(): void
    {
        $this->actingAs($this->admin)->get('/targets?month=abc')->assertOk();
        $this->actingAs($this->admin)->get('/attendance/register?month=2026-13')->assertOk();
        $this->actingAs($this->admin)->get('/leads/999999')->assertNotFound();
        $this->actingAs($this->admin)->post('/clashes/999/resolve', ['assigned_to' => 1])->assertNotFound();
        $this->actingAs($this->admin)->get('/attendance/people/999')->assertNotFound();
    }

    public function test_back_after_a_download_returns_to_the_page_not_the_file(): void
    {
        $this->actingAs($this->admin)->get('/leads')->assertOk();
        $this->get('/leads/export?format=csv')->assertOk();
        $this->get('/branding/logo', ['Sec-Fetch-Dest' => 'image']);
        // A form with a mistake goes back: it must land on /leads, not on the export file
        $res = $this->post('/tasks', ['title' => '']);
        $res->assertRedirect(url('/leads'));
    }
    public function test_each_person_chooses_light_dark_or_device_theme(): void
    {
        $this->actingAs($this->repA)->get('/')->assertOk()->assertDontSee('data-theme="', false);
        $this->post('/profile/theme', ['theme' => 'dark'])->assertRedirect()->assertCookie('crm_theme', 'dark');
        $this->assertSame('dark', $this->repA->fresh()->theme);
        $this->get('/')->assertSee('data-theme="dark"', false);
        $this->post('/profile/theme', ['theme' => 'light']);
        $this->get('/leads')->assertSee('data-theme="light"', false);
        $this->post('/profile/theme', ['theme' => 'purple'])->assertSessionHasErrors('theme');
        $this->post('/profile/theme', ['theme' => 'system']);
        $this->get('/')->assertDontSee('data-theme="', false);
        $this->actingAs($this->repB)->get('/')->assertDontSee('data-theme="', false); // one person's choice is only theirs
        // The login page follows the last choice made on this device
        auth()->logout();
        $this->withCookie('crm_theme', 'dark')->get('/login')->assertSee('data-theme="dark"', false);
        $this->withCookie('crm_theme', '"><script>')->get('/login')->assertDontSee('<script>"', false)->assertDontSee('data-theme="', false);
    }
}

