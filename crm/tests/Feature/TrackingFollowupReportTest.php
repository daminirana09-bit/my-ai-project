<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\Campaign;
use App\Models\CompanySetting;
use App\Models\FollowupLog;
use App\Models\FollowupRule;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Target;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\WhatsApp\FollowupRunner;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/** Team tracking, automatic WhatsApp follow-ups, deals report and monthly sales report. */
class TrackingFollowupReportTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 11:00:00')); // Thursday, office hours
    }

    private function lead(array $extra = []): Lead
    {
        return Lead::create($extra + ['firm_name' => 'Sharma Medicos', 'contact_name' => 'Ramesh', 'phone' => '9876500001', 'city' => 'Indore', 'status' => 'new', 'owner_id' => $this->repA->id]);
    }

    // ---------- team tracking ----------

    public function test_punch_keeps_the_place_and_can_be_made_compulsory(): void
    {
        // The browser may ask for the location on our own pages only
        $this->actingAs($this->repA)->get('/')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $this->post('/attendance/punch', ['type' => 'in', 'lat' => 22.7196, 'lng' => 75.8577, 'accuracy' => 15])->assertSessionHas('ok');
        $p = AttendancePunch::sole();
        $this->assertSame([22.7196, 75.8577, 15], [(float) $p->lat, (float) $p->lng, $p->accuracy]);
        $this->post('/attendance/punch', ['type' => 'out', 'lat' => 95, 'lng' => 75])->assertSessionHasErrors('lat');
        $this->post('/attendance/punch', ['type' => 'out'])->assertSessionHas('ok'); // location is optional by default

        CompanySetting::current()->update(['hr_config' => ['punch_location_required' => 1]]);
        CompanySetting::forget();
        $this->actingAs($this->repB)->post('/attendance/punch', ['type' => 'in'])->assertSessionHasErrors('punch');
        $this->post('/attendance/punch', ['type' => 'in', 'lat' => 22.7, 'lng' => 75.8])->assertSessionHas('ok');
    }

    public function test_visit_check_in_saves_the_clients_place_and_flags_far_check_ins(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->repA)->post("/leads/{$lead->id}/checkin", ['note' => 'Met doctor'])->assertSessionHasErrors('lat');
        $this->post("/leads/{$lead->id}/checkin", ['lat' => 22.7196, 'lng' => 75.8577, 'accuracy' => 20, 'note' => 'Met doctor'])->assertSessionHas('ok');
        $this->assertSame([22.7196, 75.8577], [(float) $lead->fresh()->lat, (float) $lead->fresh()->lng]);
        $this->post("/leads/{$lead->id}/checkin", ['lat' => 22.7200, 'lng' => 75.8577, 'accuracy' => 20])->assertSessionHas('ok'); // ~45 m
        $this->post("/leads/{$lead->id}/checkin", ['lat' => 22.7396, 'lng' => 75.8577, 'accuracy' => 20])->assertSessionHas('warn'); // ~2.2 km
        $d = LeadActivity::where('type', 'visit')->orderBy('id')->pluck('distance_m')->all();
        $this->assertNull($d[0]);
        $this->assertTrue($d[1] > 30 && $d[1] < 60 && $d[2] > 2000 && $d[2] < 2400, json_encode($d));
        $this->get("/leads/{$lead->id}")->assertSee('2.2 km from client');

        $this->actingAs($this->repB)->post("/leads/{$lead->id}/checkin", ['lat' => 22.7, 'lng' => 75.8])->assertNotFound();
        $this->actingAs($this->repA)->post("/leads/{$lead->id}/place/reset")->assertForbidden();
        $this->actingAs($this->manager)->post("/leads/{$lead->id}/place/reset")->assertSessionHas('ok');
        $this->assertNull($lead->fresh()->lat);
        // A rough location (accuracy 900 m) is not saved as the client's place
        $this->actingAs($this->repA)->post("/leads/{$lead->id}/checkin", ['lat' => 22.75, 'lng' => 75.9, 'accuracy' => 900]);
        $this->assertNull($lead->fresh()->lat);
    }

    public function test_team_day_view_and_who_may_see_it(): void
    {
        $lead = $this->lead();
        AttendancePunch::create(['user_id' => $this->repA->id, 'type' => 'in', 'punched_at' => now()->setTime(9, 35), 'lat' => 22.7196, 'lng' => 75.8577]);
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $this->repA->id, 'type' => 'call', 'outcome' => 'connected', 'occurred_at' => now()->setTime(10, 0)]);
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $this->repA->id, 'type' => 'visit', 'occurred_at' => now()->setTime(10, 30), 'lat' => 22.9676, 'lng' => 76.0534, 'distance_m' => 900]);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $lead->id, 'amount' => 25000, 'sale_date' => today(), 'status' => 'confirmed']);

        $this->actingAs($this->manager)->get('/team')->assertOk()->assertSee('Amit')->assertSee('09:35 AM')->assertSee('34.1')->assertSee('1 far');
        $this->get("/team/{$this->repA->id}")->assertOk()->assertSee('Sharma Medicos')->assertSee('google.com/maps/dir/')->assertSee('900 m from client');
        $this->actingAs($this->repA)->get('/team')->assertForbidden();
        $this->get("/team/{$this->repA->id}")->assertOk();
        $this->get("/team/{$this->repB->id}")->assertNotFound();
        $other = \App\Models\User::find($this->otherTeamRep->manager_id);
        $this->actingAs($other)->get("/team/{$this->repA->id}")->assertNotFound();
        $this->actingAs($this->manager)->get('/team?date=2026-11-11')->assertOk()->assertSee('Not punched');
    }

    // ---------- auto follow-ups ----------

    private function whatsapp(): void
    {
        CompanySetting::current()->update(['whatsapp_config' => ['access_token' => 'tok', 'app_secret' => 'sec', 'verify_token' => 'vt', 'api_version' => 'v23.0']]);
        CompanySetting::forget();
        WhatsappNumber::create(['label' => 'Company', 'display_phone' => '+91 90000 00009', 'phone_number_id' => '1009', 'user_id' => null, 'active' => true]);
        Http::fake(['graph.facebook.com/*/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.F'.uniqid()]]])]);
    }

    private function rule(string $trigger, int $after, array $extra = []): FollowupRule
    {
        return FollowupRule::create($extra + ['name' => ucfirst($trigger), 'trigger' => $trigger, 'after' => $after, 'repeat_days' => 7,
            'template_name' => 'gentle_reminder', 'template_language' => 'en', 'variables' => ['contact_name'], 'active' => true]);
    }

    private function sentTemplates(): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages'))->map(fn ($r) => $r->data())->values()->all();
    }

    public function test_each_trigger_finds_the_right_clients_once(): void
    {
        $this->whatsapp();
        $due = $this->lead(['firm_name' => 'Due Today', 'phone' => '9876500011', 'status' => 'contacted', 'next_follow_up_on' => today()]);
        $this->lead(['firm_name' => 'Already Called', 'phone' => '9876500012', 'status' => 'contacted', 'next_follow_up_on' => today()->subDay(), 'last_contact_at' => now()->subHour()]);
        $quoted = $this->lead(['firm_name' => 'Quoted', 'phone' => '9876500013', 'status' => 'quote_sent', 'last_contact_at' => now()->subDays(3)]);
        Quote::create(['number' => 'QT-1', 'lead_id' => $quoted->id, 'user_id' => $this->repA->id, 'quote_date' => today()->subDays(3), 'status' => 'sent', 'sent_at' => now()->subDays(3), 'subtotal' => 1, 'gst_total' => 0, 'grand_total' => 1]);
        $customer = $this->lead(['firm_name' => 'Old Customer', 'phone' => '9876500014', 'status' => 'won']);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $customer->id, 'amount' => 5000, 'sale_date' => today()->subDays(40), 'status' => 'confirmed']);
        $fresh = $this->lead(['firm_name' => 'Fresh', 'phone' => '9876500015', 'status' => 'won']);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $fresh->id, 'amount' => 5000, 'sale_date' => today()->subDays(5), 'status' => 'confirmed']);
        $this->lead(['firm_name' => 'Stopped', 'phone' => '9876500016', 'status' => 'contacted', 'next_follow_up_on' => today(), 'whatsapp_opt_out' => true]);
        $this->travel(-3)->hours();
        $new = $this->lead(['firm_name' => 'Brand New', 'phone' => '9876500017']);
        $this->travel(3)->hours();

        $r = app(FollowupRunner::class);
        $names = fn ($rule) => $r->due($rule)->pluck('firm_name')->sort()->values()->all();
        $this->assertSame(['Due Today'], $names($this->rule('follow_up_due', 0)));
        $this->assertSame(['Quoted'], $names($this->rule('quote_no_reply', 2)));
        $this->assertSame(['Old Customer'], $names($this->rule('reorder', 30)));
        $this->assertSame(['Brand New'], $names($this->rule('new_lead', 2)));
        $this->assertSame(['Quoted'], $names($this->rule('no_reply', 2)));

        $this->assertSame(['sent' => 5, 'failed' => 0, 'skipped' => 0], $r->run());
        $this->assertSame('gentle_reminder', $this->sentTemplates()[0]['template']['name']);
        $this->assertSame('Ramesh', $this->sentTemplates()[0]['template']['components'][0]['parameters'][0]['text']);
        $this->assertSame(5, FollowupLog::where('status', 'sent')->count());
        $this->assertSame(1, LeadActivity::where('lead_id', $due->id)->where('note', 'like', 'Automatic follow-up%')->count());
        $this->assertNotNull(WaConversation::where('lead_id', $new->id)->first(), 'chat made so the reply lands in the inbox');
        $this->assertSame(['sent' => 0, 'failed' => 0, 'skipped' => 0], $r->run(), 'never twice within the repeat days');

        // A reply after the quotation stops the quotation reminder for the next time
        $this->travel(8)->days();
        WaMessage::create(['wa_conversation_id' => WaConversation::where('lead_id', $quoted->id)->value('id'), 'direction' => 'in', 'body' => 'ok', 'type' => 'text', 'status' => 'received', 'sent_at' => now()->subDay()]);
        $this->assertSame([], $names(FollowupRule::where('trigger', 'quote_no_reply')->first()));
    }

    public function test_office_hours_daily_limit_24_hour_text_and_missing_template(): void
    {
        $this->whatsapp();
        $a = $this->lead(['firm_name' => 'A', 'phone' => '9876500021', 'status' => 'contacted', 'next_follow_up_on' => today()]);
        $this->lead(['firm_name' => 'B', 'phone' => '9876500022', 'status' => 'contacted', 'next_follow_up_on' => today()]);
        $rule = $this->rule('follow_up_due', 0, ['text' => 'Namaste {name}, any update on your order? – {rep}, {company}']);

        $this->travelTo(Carbon::parse('2026-11-12 20:00:00'));
        $this->assertSame(0, app(FollowupRunner::class)->run()['sent'], 'after office hours');
        $this->travelTo(Carbon::parse('2026-11-15 11:00:00'));
        $this->assertSame(0, app(FollowupRunner::class)->run()['sent'], 'Sunday');

        $this->travelTo(Carbon::parse('2026-11-12 11:00:00'));
        // A wrote in the last 24 hours: the rule's own text goes, not the template
        $conv = WaConversation::create(['whatsapp_number_id' => WhatsappNumber::first()->id, 'wa_id_hash' => Lead::hashPhone('919876500021'), 'wa_id' => '919876500021',
            'lead_id' => $a->id, 'language' => 'en', 'last_inbound_at' => now()->subHours(2), 'last_message_at' => now()->subHours(2)]);
        CompanySetting::current()->update(['whatsapp_config' => CompanySetting::current()->whatsapp_config + ['daily_limit' => 1]]);
        CompanySetting::forget();
        $this->assertSame(1, app(FollowupRunner::class)->run()['sent'], 'daily limit 1');
        $this->assertSame(0, Campaign::remainingToday());
        $first = $this->sentTemplates()[0];
        $this->assertSame('text', $first['type']);
        $this->assertSame('Namaste Ramesh, any update on your order? – Amit, Test Pharma Pvt Ltd', $first['text']['body']);

        // No template and the client has not written: skipped with a reason
        CompanySetting::current()->update(['whatsapp_config' => array_merge(CompanySetting::current()->whatsapp_config, ['daily_limit' => 0])]);
        CompanySetting::forget();
        $rule->update(['template_name' => null]);
        $this->assertSame(['sent' => 0, 'failed' => 0, 'skipped' => 1], app(FollowupRunner::class)->run());
        $this->assertStringContainsString('24 hours', FollowupLog::where('status', 'skipped')->value('error'));
    }

    public function test_rules_page_is_for_people_who_may_send_campaigns(): void
    {
        $this->actingAs($this->repA)->get('/followups')->assertForbidden();
        $this->actingAs($this->admin)->get('/followups')->assertOk()->assertSee('Add a follow-up rule');
        $this->post('/followups', ['name' => 'X', 'trigger' => 'reorder', 'after' => 30, 'repeat_days' => 30])->assertSessionHasErrors('template_name');
        $this->post('/followups', ['name' => 'Reorder reminder', 'trigger' => 'reorder', 'after' => 30, 'repeat_days' => 30, 'template_name' => 'reorder_nudge', 'template_language' => 'hi', 'var' => ['contact_name', 'company']])->assertSessionHas('ok');
        $r = FollowupRule::sole();
        $this->assertSame(['contact_name', 'company'], $r->variables);
        $this->get('/followups')->assertSee('Reorder reminder')->assertSee('reorder_nudge');
        $this->put("/followups/{$r->id}", ['name' => 'Reorder', 'trigger' => 'reorder', 'after' => 45, 'repeat_days' => 30, 'template_name' => 'reorder_nudge'])->assertSessionHas('ok');
        $this->assertFalse($r->fresh()->active);
        $this->delete("/followups/{$r->id}")->assertSessionHas('ok');
        $this->assertSame(0, FollowupRule::count());
    }

    // ---------- reports ----------

    public function test_deals_report_numbers_and_visibility(): void
    {
        $open = $this->lead(['firm_name' => 'Open One', 'status' => 'negotiation', 'expected_value' => 100000, 'source' => 'IndiaMART']);
        $this->lead(['firm_name' => 'Open Two', 'phone' => '9876500002', 'status' => 'new', 'expected_value' => 50000, 'source' => 'IndiaMART']);
        $won = $this->lead(['firm_name' => 'Winner', 'phone' => '9876500003', 'status' => 'interested', 'expected_value' => 80000, 'source' => 'Reference']);
        $this->travel(4)->days();
        $won->update(['status' => 'won']);
        $this->assertNotNull($won->fresh()->won_at, 'won date kept automatically');
        $lost = $this->lead(['firm_name' => 'Gone', 'phone' => '9876500004', 'owner_id' => $this->repB->id, 'expected_value' => 30000]);
        $lost->update(['status' => 'lost', 'lost_reason' => 'Price too high']);
        $this->lead(['firm_name' => 'Other team', 'phone' => '9876500005', 'owner_id' => $this->otherTeamRep->id, 'status' => 'negotiation', 'expected_value' => 999999]);

        $r = $this->actingAs($this->manager)->get('/reports/deals?from=2026-11-01&to=2026-11-30')->assertOk()->viewData('r');
        $this->assertSame([2, 150000.0, 1, 80000.0, 1, 50], [$r['open_count'], $r['open_value'], $r['won_count'], $r['won_value'], $r['lost_count'], $r['rate']]);
        $this->assertSame(85000.0, $r['weighted']); // 1,00,000 × 80% + 50,000 × 10%
        $this->assertSame(4.0, $r['avg_days']);
        $this->assertSame(['Price too high' => 1], $r['reasons']->all());
        $this->assertSame(['Amit', 'Bhavna'], $r['people']->pluck('user.name')->all());

        $mine = $this->actingAs($this->repA)->get('/reports/deals?from=2026-11-01&to=2026-11-30')->viewData('r');
        $this->assertSame([2, 1, 0], [$mine['open_count'], $mine['won_count'], $mine['lost_count']]);
        $x = $this->actingAs($this->manager)->get('/reports/deals?from=2026-11-01&to=2026-11-30&export=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', file_get_contents($x->baseResponse->getFile()->getPathname()));
        // Reopening a won deal clears the won date
        $won->update(['status' => 'negotiation']);
        $this->assertNull($won->fresh()->won_at);
        $this->assertNotNull($open);
    }

    public function test_monthly_sales_report_per_salesperson(): void
    {
        $old = $this->lead(['firm_name' => 'Old Client', 'status' => 'won']);
        $newc = $this->lead(['firm_name' => 'New Client', 'phone' => '9876500002', 'status' => 'won']);
        Target::create(['user_id' => $this->repA->id, 'month' => '2026-11', 'amount' => 100000]);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $old->id, 'amount' => 40000, 'sale_date' => '2026-10-10', 'status' => 'confirmed']);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $old->id, 'amount' => 30000, 'sale_date' => '2026-11-05', 'status' => 'confirmed']);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $newc->id, 'amount' => 20000, 'sale_date' => '2026-11-08', 'status' => 'confirmed']);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $newc->id, 'amount' => 9000, 'sale_date' => '2026-11-09', 'status' => 'pending']);
        LeadActivity::create(['lead_id' => $old->id, 'user_id' => $this->repA->id, 'type' => 'call', 'occurred_at' => now()]);
        LeadActivity::create(['lead_id' => $old->id, 'user_id' => $this->repA->id, 'type' => 'visit', 'occurred_at' => now()]);
        \App\Models\AttendanceDay::create(['user_id' => $this->repA->id, 'date' => '2026-11-02', 'status' => 'full_day', 'worked_minutes' => 500]);
        \App\Models\AttendanceDay::create(['user_id' => $this->repA->id, 'date' => '2026-11-03', 'status' => 'half_day', 'worked_minutes' => 300]);

        $rows = $this->actingAs($this->manager)->get('/reports/monthly?month=2026-11')->assertOk()->assertSee('Amit')->viewData('rows');
        $a = $rows->firstWhere('user.id', $this->repA->id);
        $this->assertSame([100000.0, 50000.0, 50.0, 2, 9000.0, 1, 1, 40000.0, 25.0, 1, 1, 1.5],
            [$a->target, $a->achieved, (float) $a->percent, $a->orders, $a->pending, $a->new_customers, $a->repeat_customers, $a->last_month, (float) $a->growth, $a->calls, $a->visits, $a->present]);
        $this->assertSame(['Amit', 'Bhavna', 'Manager West'], $rows->pluck('user.name')->all());
        $this->assertSame(['Amit'], $this->actingAs($this->repA)->get('/reports/monthly?month=2026-11')->viewData('rows')->pluck('user.name')->all());

        $this->actingAs($this->manager);
        $x = $this->get('/reports/monthly?month=2026-11&export=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', file_get_contents($x->baseResponse->getFile()->getPathname()));
        $this->assertStringStartsWith('%PDF', $this->get('/reports/monthly?month=2026-11&export=pdf')->assertOk()->getContent());
        $this->get('/reports/monthly?month=bad')->assertOk();
    }
}
