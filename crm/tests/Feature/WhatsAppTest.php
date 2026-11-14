<?php

namespace Tests\Feature;

use App\Jobs\SendCampaign;
use App\Models\Campaign;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadClash;
use App\Models\Quote;
use App\Models\Product;
use App\Models\Task;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\DailyAutomations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/** WhatsApp webhook, inbox, AI cover, campaigns, website form, quotations and automations. Meta is faked. */
class WhatsAppTest extends CrmTestCase
{
    private WhatsappNumber $amitNo;
    private WhatsappNumber $companyNo;

    protected function setUp(): void
    {
        parent::setUp();
        // A Thursday, 11 AM: inside office hours (09:30–18:30), so results never depend on when the tests run.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 11:00:00'));
        CompanySetting::current()->update(['whatsapp_config' => ['access_token' => 'tok', 'app_secret' => 'sec', 'verify_token' => 'vt', 'api_version' => 'v23.0'], 'lead_capture_token' => str_repeat('a', 40)]);
        CompanySetting::forget();
        $this->amitNo = WhatsappNumber::create(['label' => 'Amit', 'display_phone' => '+91 90000 00001', 'phone_number_id' => '1001', 'user_id' => $this->repA->id, 'active' => true]);
        $this->companyNo = WhatsappNumber::create(['label' => 'Company', 'display_phone' => '+91 90000 00009', 'phone_number_id' => '1009', 'user_id' => null, 'active' => true]);
        Http::fake([
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.OUT'.uniqid()]]]),
            'graph.facebook.com/*/media' => Http::response(['id' => 'media-1']),
        ]);
    }

    private function hook(string $phoneNumberId, string $from, string $text, string $wamid = null, string $name = 'Murugan')
    {
        $payload = json_encode(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $phoneNumberId],
            'contacts' => [['wa_id' => $from, 'profile' => ['name' => $name]]],
            'messages' => [['id' => $wamid ?? 'wamid.IN'.uniqid(), 'from' => $from, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);

        return $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'sec')], $payload);
    }

    // ---------- webhook ----------

    public function test_webhook_verification(): void
    {
        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=vt&hub.challenge=12345')->assertOk()->assertSeeText('12345');
        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();
    }

    public function test_unsigned_or_wrongly_signed_messages_are_refused(): void
    {
        $body = json_encode(['entry' => []]);
        $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertForbidden();
        $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertForbidden();
        $this->assertSame(0, WaMessage::count());
    }

    public function test_message_creates_chat_and_lead_with_language_and_request(): void
    {
        $this->hook('1001', '919876500001', 'வணக்கம், மல்டிவைட்டமின் கேப்ஸ்யூல் விலை என்ன?', 'wamid.A')->assertOk();
        $c = WaConversation::first();
        $this->assertSame('ta', $c->language);
        $this->assertSame('price', $c->last_intent);
        $this->assertSame(1, $c->unread);
        $lead = Lead::first();
        $this->assertSame($this->repA->id, $lead->owner_id);
        $this->assertSame('WhatsApp', $lead->source);
        $this->assertSame('9876500001', Lead::normalizePhone($lead->phone));
        // Message text is encrypted in the database.
        $raw = \DB::table('wa_messages')->value('body');
        $this->assertStringNotContainsString('வணக்கம்', $raw);
        // Meta retries: the same message id is stored once.
        $this->hook('1001', '919876500001', 'வணக்கம், மல்டிவைட்டமின் கேப்ஸ்யூல் விலை என்ன?', 'wamid.A');
        $this->assertSame(1, WaMessage::count());
        Http::assertNothingSent(); // Amit is not away: no automatic reply
    }

    public function test_detects_many_languages_and_requests(): void
    {
        $cases = [
            ['मेरा ऑर्डर कब आएगा?', 'hi', 'order_status'], ['bhai rate list bhej do', 'hing', 'price'], ['Please send COA for batch 22', 'en', 'documents'],
            ['નમસ્તે, Pan-D નો ભાવ શું છે?', 'gu', 'price'], ['আমাদের পেমেন্ট এই সপ্তাহে হবে', 'bn', 'payment'], ['आम्हाला सॅम्पल पाहिजे आहे', 'mr', 'other'],
            ['URGENT: wrong batch received', 'en', 'complaint'], ['ਸਤ ਸ੍ਰੀ ਅਕਾਲ ਜੀ', 'pa', 'greeting'], ['آرڈر کب آئے گا', 'ur', 'new_order'],
        ];
        $lang = app(\App\Services\WhatsApp\LanguageDetector::class);
        $intent = app(\App\Services\WhatsApp\IntentDetector::class);
        foreach ($cases as [$text, $l, $i]) {
            $this->assertSame($l, $lang->detect($text), $text);
            if ($i !== 'other') {
                $this->assertSame($i, $intent->detect($text), $text);
            }
        }
    }

    public function test_client_of_another_rep_writing_to_amit_is_a_clash(): void
    {
        $lead = Lead::create(['firm_name' => 'Bhavna Client', 'phone' => '9876500002', 'status' => 'new', 'owner_id' => $this->repB->id]);
        $this->hook('1001', '919876500002', 'Hello, need rate list');
        $this->assertSame(1, Lead::count());
        $this->assertSame($lead->id, WaConversation::first()->lead_id);
        $this->assertDatabaseHas('lead_clashes', ['lead_id' => $lead->id, 'other_user_id' => $this->repA->id, 'reason' => 'contacted']);
    }

    public function test_company_number_gives_new_lead_to_rep_with_fewest(): void
    {
        Lead::create(['firm_name' => 'x', 'status' => 'new', 'owner_id' => $this->repA->id, 'created_via' => 'whatsapp']);
        $this->hook('1009', '919876500003', 'Hi');
        $owner = Lead::where('phone_hash', Lead::hashPhone('9876500003'))->value('owner_id');
        $this->assertNotSame($this->repA->id, $owner);
        $this->assertContains($owner, [$this->repB->id, $this->otherTeamRep->id]);
    }

    // ---------- AI cover ----------

    public function test_away_rep_gets_auto_reply_in_client_language_once(): void
    {
        $this->repA->update(['away_type' => 'half_day', 'away_until' => now()->addHours(3), 'salutation' => 'sir']);
        $this->hook('1001', '919876500004', 'வணக்கம், ஆர்டர் எப்போது வரும்?', null, 'Murugan');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v23.0/1001/messages') && $r['to'] === '919876500004'
            && str_contains($r['text']['body'], 'தானியங்கி பதில்') && str_contains($r['text']['body'], 'Amit Sir') && str_contains($r['text']['body'], 'Murugan'));
        $c = WaConversation::first();
        $this->assertTrue($c->waiting_callback);
        $this->assertNotNull($c->auto_replied_at);
        $this->assertSame(1, WaMessage::where('type', 'auto')->count());
        $this->hook('1001', '919876500004', 'Hello?');
        $this->assertSame(1, WaMessage::where('type', 'auto')->count()); // not twice
        // Waiting call appears in Amit's reminders; "I am back" ends cover.
        $this->actingAs($this->repA)->get('/')->assertSee('Murugan');
        $this->actingAs($this->repA)->post('/availability', ['back' => 1])->assertSessionHas('ok');
        $this->assertFalse($this->repA->fresh()->isAway());
    }

    public function test_leave_marked_in_attendance_also_triggers_cover(): void
    {
        $this->actingAs($this->manager)->post("/attendance/people/{$this->repA->id}/day", ['date' => today()->toDateString(), 'status' => 'leave']);
        $this->hook('1001', '919876500005', 'Rate list please');
        Http::assertSentCount(1);
    }

    public function test_cover_can_be_switched_off(): void
    {
        CompanySetting::current()->update(['automations' => ['away_auto_reply' => false]]);
        CompanySetting::forget();
        $this->repA->update(['away_type' => 'leave', 'away_until' => now()->addDay()]);
        $this->hook('1001', '919876500006', 'Rate list please');
        Http::assertNothingSent();
    }

    public function test_after_office_hours_clients_get_auto_reply_with_next_opening_time(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 19:15:00')); // after 6:30 PM
        $this->hook('1001', '919876500021', 'Rate list please');
        Http::assertSent(fn ($r) => str_contains($r['text']['body'] ?? '', '13 Nov, 9:30 AM'));
        $this->assertTrue(WaConversation::first()->waiting_callback);

        // Saturday evening → Monday morning (Sunday is the weekly off)
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 20:00:00'));
        $this->hook('1001', '919876500022', 'Rate list please');
        Http::assertSent(fn ($r) => str_contains($r['text']['body'] ?? '', '16 Nov, 9:30 AM'));

        // A person with their own timing (till 8 PM) is still at work at 7:15 PM
        $this->repA->update(['office_end' => '20:00']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 19:15:00'));
        $this->hook('1001', '919876500023', 'Rate list please');
        $this->assertFalse(WaConversation::where('contact_name', 'Murugan')->latest('id')->first()->waiting_callback);

        CompanySetting::current()->update(['automations' => ['after_hours_auto_reply' => false]]);
        CompanySetting::forget();
        $this->repA->update(['office_end' => null]);
        $this->hook('1001', '919876500024', 'Rate list please');
        $this->assertSame(2, WaMessage::where('type', 'auto')->count());
    }

    public function test_absent_without_marking_or_gone_after_punch_out_gets_auto_reply(): void
    {
        // Company uses attendance: Amit punched in yesterday
        \App\Models\AttendancePunch::create(['user_id' => $this->repA->id, 'type' => 'in', 'punched_at' => now()->subDay()->setTime(9, 25)]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 10:15:00')); // only 45 min after start: may be late, not absent
        $this->hook('1001', '919876500031', 'Hello');
        $this->assertSame(0, WaMessage::where('type', 'auto')->count());

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 11:00:00')); // no punch an hour after start
        $this->hook('1001', '919876500032', 'Hello');
        $this->assertSame(1, WaMessage::where('type', 'auto')->count());
        Http::assertSent(fn ($r) => str_contains($r['text']['body'] ?? '', '13 Nov, 9:30 AM'));

        // He punches in: cover stops and he sees who is waiting
        $res = $this->actingAs($this->repA)->post('/attendance/punch', ['type' => 'in']);
        $this->assertStringContainsString('1 clients are waiting for your call', session('ok'));
        $this->hook('1001', '919876500033', 'Hello');
        $this->assertSame(1, WaMessage::where('type', 'auto')->count());

        // Out for lunch (13:30–14:00) is not "gone"
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 13:35:00'));
        $this->actingAs($this->repA)->post('/attendance/punch', ['type' => 'out']);
        $this->hook('1001', '919876500034', 'Hello');
        $this->assertSame(1, WaMessage::where('type', 'auto')->count());

        // Still out at 3 PM: gone for the day
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 15:00:00'));
        $this->hook('1001', '919876500035', 'Hello');
        $this->assertSame(2, WaMessage::where('type', 'auto')->count());
    }

    public function test_company_that_does_not_use_attendance_is_never_treated_as_absent(): void
    {
        $this->hook('1001', '919876500041', 'Hello'); // 11 AM, nobody ever punched in
        Http::assertNothingSent();
    }

    public function test_urgent_message_alerts_admin_and_manager(): void
    {
        $this->hook('1001', '919876500007', 'URGENT: wrong batch delivered, damaged strips');
        $this->assertTrue(WaConversation::first()->urgent);
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(1, $this->manager->notifications()->count());
        $this->actingAs($this->admin)->get('/notifications')->assertSee('Urgent WhatsApp');
    }

    public function test_stop_opts_out_and_status_updates_never_go_back(): void
    {
        $this->hook('1001', '919876500008', 'STOP');
        $this->assertTrue(Lead::first()->whatsapp_opt_out);
        $m = WaMessage::create(['wa_conversation_id' => WaConversation::first()->id, 'direction' => 'out', 'body' => 'x', 'wamid' => 'wamid.S', 'status' => 'sent', 'sent_at' => now()]);
        foreach (['read', 'delivered'] as $st) {
            $p = json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => '1001'], 'statuses' => [['id' => 'wamid.S', 'status' => $st]]]]]]]]);
            $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $p, 'sec')], $p)->assertOk();
        }
        $this->assertSame('read', $m->fresh()->status);
    }

    // ---------- inbox ----------

    public function test_inbox_visibility_suggestions_and_sending(): void
    {
        $this->hook('1001', '919876500010', 'नमस्ते, Pan-D का रेट क्या है?');
        $c = WaConversation::first();
        $this->actingAs($this->repB)->get("/inbox/{$c->id}")->assertNotFound();
        $this->actingAs($this->otherTeamRep)->get('/inbox')->assertDontSee('919876500010');
        $this->actingAs($this->manager)->get("/inbox/{$c->id}")->assertOk();
        $this->repA->update(['locale' => 'en']);
        $this->actingAs($this->repA->fresh())->get("/inbox/{$c->id}")->assertOk()
            ->assertSee('रेट लिस्ट', false)->assertSee('We will send our latest rate list', false);
        $this->assertSame(0, $c->fresh()->unread);
        $this->actingAs($this->repA)->post("/inbox/{$c->id}/send", ['body' => 'Rate list attached'])->assertSessionHas('ok');
        Http::assertSent(fn ($r) => $r['text']['body'] === 'Rate list attached' && $r['to'] === '919876500010');
        $this->actingAs($this->repB)->post("/inbox/{$c->id}/send", ['body' => 'hack'])->assertNotFound();
    }

    public function test_no_free_text_after_24_hours_and_failure_is_shown(): void
    {
        $this->hook('1001', '919876500011', 'Hi');
        $c = WaConversation::first();
        $c->forceFill(['last_inbound_at' => now()->subHours(25)])->save();
        $this->actingAs($this->repA)->post("/inbox/{$c->id}/send", ['body' => 'Hello'])->assertSessionHasErrors('body');
        Http::assertNothingSent();

        $c->forceFill(['last_inbound_at' => now()])->save();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token', 'code' => 190]], 401)]);
        $this->actingAs($this->repA)->post("/inbox/{$c->id}/send", ['body' => 'Hello'])->assertSessionHasErrors('body');
        $this->assertSame('failed', WaMessage::where('direction', 'out')->value('status'));
        $this->assertStringContainsString('190', WaMessage::where('direction', 'out')->value('error'));
    }

    public function test_availability_rules(): void
    {
        $this->actingAs($this->repA)->post('/availability', ['away_type' => 'leave', 'back_date' => today()->addDays(2)->toDateString(), 'salutation' => 'madam', 'backup_user_id' => $this->repB->id])->assertSessionHas('ok');
        $u = $this->repA->fresh();
        $this->assertTrue($u->isAway());
        $this->assertSame('Amit Madam', $u->calledAs());
        $this->actingAs($this->repA)->post("/availability/{$this->repB->id}", ['away_type' => 'meeting', 'back_time' => '23:59', 'salutation' => 'ji'])->assertForbidden();
        $this->actingAs($this->manager)->post("/availability/{$this->repB->id}", ['away_type' => 'meeting', 'back_time' => '23:59', 'salutation' => 'ji'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post("/availability/{$this->otherTeamRep->id}", ['away_type' => 'meeting', 'back_time' => '23:59', 'salutation' => 'ji'])->assertForbidden();
        $this->actingAs($this->repA)->post('/availability', ['away_type' => 'half_day', 'back_time' => '00:00', 'salutation' => 'ji'])->assertSessionHasErrors('back_time');
    }

    // ---------- campaigns ----------

    public function test_campaign_sends_template_to_visible_opted_in_leads(): void
    {
        Queue::fake();
        Lead::create(['firm_name' => 'A1', 'contact_name' => 'Ravi', 'phone' => '9811100001', 'status' => 'won', 'owner_id' => $this->repA->id]);
        Lead::create(['firm_name' => 'A2', 'phone' => '9811100002', 'status' => 'won', 'owner_id' => $this->repB->id, 'whatsapp_opt_out' => true]);
        Lead::create(['firm_name' => 'A3', 'status' => 'won', 'owner_id' => $this->repB->id]); // no phone
        Lead::create(['firm_name' => 'Other team', 'phone' => '9811100004', 'status' => 'won', 'owner_id' => $this->otherTeamRep->id]);
        $this->actingAs($this->repA)->get('/campaigns')->assertForbidden();
        $this->actingAs($this->manager)->post('/campaigns', ['name' => 'Diwali', 'whatsapp_number_id' => $this->companyNo->id, 'template_name' => 'diwali_offer', 'template_language' => 'hi', 'status' => 'won', 'var' => ['contact_name', 'company']])->assertRedirect();
        $camp = Campaign::first();
        $this->assertSame(1, $camp->total);
        Queue::assertPushed(SendCampaign::class);
        (new SendCampaign($camp->id))->handle(app(\App\Services\WhatsApp\Client::class));
        Http::assertSent(fn ($r) => $r['type'] === 'template' && $r['template']['name'] === 'diwali_offer' && $r['to'] === '919811100001'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Ravi' && $r['template']['components'][0]['parameters'][1]['text'] === 'Test Pharma Pvt Ltd');
        $this->assertSame('done', $camp->fresh()->status);
        $this->assertSame('sent', $camp->recipients()->value('status'));
        $this->actingAs($this->manager)->get("/campaigns/{$camp->id}")->assertOk();
        $this->actingAs($this->manager)->post('/campaigns', ['name' => 'X', 'whatsapp_number_id' => $this->companyNo->id, 'template_name' => 'BAD NAME', 'template_language' => 'hi'])->assertSessionHasErrors('template_name');
    }

    // ---------- website form ----------

    public function test_website_form_creates_lead_once(): void
    {
        $url = '/webhook/lead/'.str_repeat('a', 40);
        $this->post('/webhook/lead/'.str_repeat('b', 40), ['name' => 'X', 'phone' => '9811122222'])->assertNotFound();
        $this->postJson($url, ['name' => 'Ravi Kumar', 'phone' => '98111 22222', 'firm' => 'Ravi Pharma', 'message' => 'Need PCD'])->assertOk()->assertJson(['ok' => true]);
        $lead = Lead::first();
        $this->assertSame('Ravi Pharma', $lead->firm_name);
        $this->assertSame('Website', $lead->source);
        $this->assertNotNull($lead->owner_id);
        $this->postJson($url, ['name' => 'Ravi Kumar', 'phone' => '9811122222', 'message' => 'Again'])->assertOk();
        $this->assertSame(1, Lead::count());
        $this->assertSame(1, $lead->activities()->count());
        $this->postJson($url, ['name' => 'Bot', 'phone' => '9811133333', 'website' => 'spam'])->assertOk();
        $this->assertSame(1, Lead::count());
        $this->postJson($url, ['name' => 'Bad', 'phone' => 'abc'])->assertStatus(422);
    }

    // ---------- quotations ----------

    public function test_quote_totals_pdf_and_whatsapp_send(): void
    {
        $lead = Lead::create(['firm_name' => 'Quote Client', 'phone' => '9811144444', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $p = Product::create(['name' => 'Pan-D', 'packing' => '10x10', 'rate' => 22.40, 'gst_percent' => 5, 'unit' => 'strip', 'hsn' => '3004']);
        $this->actingAs($this->repA)->post('/quotes', ['lead_id' => $lead->id, 'valid_days' => 15, 'freight' => 500, 'items' => [
            ['product_id' => $p->id, 'qty' => 10000, 'discount_percent' => 5],
            ['description' => 'Printed cartons', 'qty' => 10000, 'rate' => 0.45, 'gst_percent' => 18],
            ['product_id' => '', 'qty' => ''],
        ]])->assertRedirect();
        $q = Quote::first();
        $this->assertSame(2, $q->items()->count());
        $this->assertEquals(212800 + 4500, (float) $q->subtotal);        // 10000×22.40×0.95 + 10000×0.45
        $this->assertEquals(10640 + 810, (float) $q->gst_total);         // 5% and 18%
        $this->assertEquals(217300 + 11450 + 500, (float) $q->grand_total);
        $this->assertSame('quote_sent', $lead->fresh()->status);
        $pdf = $this->actingAs($this->repA)->get("/quotes/{$q->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($this->repB)->get("/quotes/{$q->id}")->assertNotFound();
        $this->actingAs($this->repB)->get("/quotes/{$q->id}/pdf")->assertNotFound();

        // Sending needs an open 24-hour window.
        $this->actingAs($this->repA)->post("/quotes/{$q->id}/send")->assertSessionHasErrors('send');
        $this->hook('1001', '919811144444', 'Please send quote');
        $this->actingAs($this->repA)->post("/quotes/{$q->id}/send")->assertSessionHas('ok');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/1001/media'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/1001/messages') && ($r['type'] ?? '') === 'document' && $r['document']['id'] === 'media-1');
        $this->assertSame('sent', $q->fresh()->status);
        $this->actingAs($this->repA)->post('/quotes', ['lead_id' => $lead->id, 'valid_days' => 15, 'items' => [['qty' => '']]])->assertSessionHasErrors('items');
    }

    // ---------- automations ----------

    public function test_daily_automations_run_once(): void
    {
        $l = Lead::create(['firm_name' => 'Cold Client', 'status' => 'contacted', 'owner_id' => $this->repA->id]);
        $l->forceFill(['created_at' => now()->subDays(40)])->save();
        Lead::create(['firm_name' => 'Late FU', 'status' => 'interested', 'owner_id' => $this->repB->id, 'next_follow_up_on' => today()->subDays(5)]);
        $r = app(DailyAutomations::class)->run();
        $this->assertSame(1, $r['stale_tasks']);
        $this->assertSame(1, Task::where('assigned_to', $this->repA->id)->where('lead_id', $l->id)->count());
        $this->assertGreaterThanOrEqual(1, $this->manager->notifications()->count());
        app(DailyAutomations::class)->run();
        $this->assertSame(1, Task::where('lead_id', $l->id)->count()); // no duplicate task
        $this->artisan('crm:automations')->assertSuccessful();
        $this->artisan('crm:check')->assertSuccessful();
    }

    public function test_admin_pages_render_and_save(): void
    {
        foreach (['/whatsapp', '/automations', '/system', '/ready-replies?lang=ta', '/ready-replies?lang=hing', '/campaigns/new?preview=1&status=won', '/quotes', '/quotes/new', '/inbox?show=urgent'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($this->admin)->post('/whatsapp', ['api_version' => 'v23.0', 'access_token' => ''])->assertSessionHasNoErrors();
        CompanySetting::forget();
        $this->assertSame('tok', CompanySetting::current()->wa('access_token')); // empty box keeps the saved token
        $this->actingAs($this->admin)->post('/whatsapp/numbers', ['label' => 'Bhavna', 'display_phone' => '+91 1', 'phone_number_id' => '109876543210002', 'user_id' => $this->repB->id, 'active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/whatsapp/numbers', ['label' => 'Dup', 'display_phone' => '+91 1', 'phone_number_id' => '109876543210002'])->assertSessionHasErrors('phone_number_id');
        $this->actingAs($this->admin)->post('/whatsapp/numbers', ['label' => 'Short', 'display_phone' => '+91 1', 'phone_number_id' => '12'])->assertSessionHasErrors('phone_number_id');
        $this->actingAs($this->admin)->post('/ready-replies', ['language' => 'ta', 'body' => ['price' => 'புதிய விலை {name}']])->assertSessionHas('ok');
        $this->assertSame('புதிய விலை {name}', \App\Models\ReplyTemplate::where('language', 'ta')->where('intent', 'price')->value('body'));
        $this->actingAs($this->admin)->post('/automations', ['away_auto_reply' => 1, 'assign_pool' => [$this->repB->id, 99999]])->assertSessionHas('ok');
        CompanySetting::forget();
        $this->assertSame([$this->repB->id], CompanySetting::current()->automations['assign_pool']);
        foreach (['/whatsapp', '/automations', '/system'] as $url) {
            $this->actingAs($this->manager)->get($url)->assertForbidden();
        }
        $this->actingAs($this->repA)->get('/ready-replies')->assertForbidden();
    }
}
