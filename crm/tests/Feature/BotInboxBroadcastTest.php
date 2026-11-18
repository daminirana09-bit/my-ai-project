<?php

namespace Tests\Feature;

use App\Jobs\SendCampaign;
use App\Models\BotAnswer;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Sale;
use App\Models\Task;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\Translation\Provider;
use App\Services\Translation\Translator;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Chatbot, team inbox and broadcasts. Meta is faked. */
class BotInboxBroadcastTest extends CrmTestCase
{
    private WhatsappNumber $amitNo;

    private WhatsappNumber $companyNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 11:00:00')); // Thursday, office hours
        CompanySetting::current()->update(['whatsapp_config' => ['access_token' => 'tok', 'app_secret' => 'sec', 'verify_token' => 'vt', 'api_version' => 'v23.0']]);
        CompanySetting::forget();
        $this->amitNo = WhatsappNumber::create(['label' => 'Amit', 'display_phone' => '+91 90000 00001', 'phone_number_id' => '1001', 'user_id' => $this->repA->id, 'active' => true]);
        $this->companyNo = WhatsappNumber::create(['label' => 'Company', 'display_phone' => '+91 90000 00009', 'phone_number_id' => '1009', 'user_id' => null, 'active' => true]);
        Http::fake([
            'graph.facebook.com/*/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.OUT'.uniqid()]]]),
            'graph.facebook.com/*/media' => Http::response(['id' => 'media-1']),
        ]);
        Product::create(['name' => 'Pantop-D Capsule', 'composition' => 'Pantoprazole 40 mg + Domperidone 30 mg', 'packing' => '10x10', 'rate' => 85.5, 'unit' => 'strip', 'gst_percent' => 12, 'moq' => 50, 'active' => true]);
        Product::create(['name' => 'Azee 500 Tablet', 'rate' => 70, 'unit' => 'strip', 'gst_percent' => 12, 'active' => true]);
    }

    private function bot(array $cfg = []): void
    {
        CompanySetting::current()->update(['bot_config' => $cfg + ['enabled' => true, 'mode' => 'always']]);
        CompanySetting::forget();
    }

    private function hook(string $phoneNumberId, string $from, string $text, ?array $button = null)
    {
        $m = ['id' => 'wamid.IN'.uniqid(), 'from' => $from, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]];
        if ($button) {
            $m = ['id' => 'wamid.IN'.uniqid(), 'from' => $from, 'timestamp' => (string) now()->timestamp, 'type' => 'interactive',
                'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => $button[0], 'title' => $button[1]]]];
        }
        $payload = json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => $phoneNumberId],
            'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Ramesh']]], 'messages' => [$m]]]]]]]);

        return $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'sec')], $payload);
    }

    private function known(string $phone = '919876500001', array $extra = []): Lead
    {
        $lead = Lead::create($extra + ['firm_name' => 'Sharma Medicos', 'contact_name' => 'Ramesh', 'phone' => $phone, 'city' => 'Indore', 'status' => 'new', 'owner_id' => $this->repA->id]);

        return $lead;
    }

    /** Bot messages sent to Meta, in order. */
    private function botSent(): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn ($r) => str_ends_with($r->url(), '/messages'))
            ->map(fn ($r) => $r->data())->values()->all();
    }

    private function lastBot(): WaMessage
    {
        return WaMessage::where('type', 'bot')->orderByDesc('id')->firstOrFail();
    }

    // ---------- chatbot ----------

    public function test_bot_is_off_by_default_and_nothing_changes(): void
    {
        $this->known();
        $this->hook('1001', '919876500001', 'hello')->assertOk();
        $this->assertSame(0, WaMessage::where('type', 'bot')->count());
        $this->assertSame([], $this->botSent());
    }

    public function test_new_client_gets_welcome_menu_and_details_go_into_the_lead(): void
    {
        $this->bot();
        $this->hook('1009', '919876500002', 'Hello')->assertOk();
        $sent = $this->botSent();
        $this->assertSame('interactive', $sent[0]['type']);
        $this->assertSame(['bot_price', 'bot_order', 'bot_human'], array_column(array_column($sent[0]['interactive']['action']['buttons'], 'reply'), 'id'));
        $this->assertStringContainsString('Welcome to Test Pharma Pvt Ltd', $sent[0]['interactive']['body']['text']);
        $this->assertStringContainsString('firm', $sent[1]['text']['body']);
        $c = WaConversation::sole();
        $this->assertSame('ask_firm', $c->bot_step);
        $this->assertNotNull($c->assigned_to, 'company number chat goes to a salesperson');

        $this->hook('1009', '919876500002', 'Shree Ganesh Medical Store');
        $this->hook('1009', '919876500002', 'Ujjain');
        $lead = Lead::sole();
        $this->assertSame(['Shree Ganesh Medical Store', 'Ujjain'], [$lead->firm_name, $lead->city]);
        $this->assertNull($c->fresh()->bot_step);
        $this->assertStringContainsString('How can we help', $this->lastBot()->body);
    }

    public function test_product_rate_answer_comes_from_the_product_list(): void
    {
        $this->bot();
        $this->known();
        $this->hook('1001', '919876500001', 'hi');
        $this->hook('1001', '919876500001', 'pantop d ka rate kya hai?');
        $body = $this->lastBot()->body;
        $this->assertStringContainsString('*Pantop-D Capsule*', $body);
        $this->assertStringContainsString('₹85.50 / strip + 12% GST', $body);
        $this->assertStringContainsString('Pantoprazole 40 mg', $body);
        $this->assertStringNotContainsString('Azee', $body);
        $this->assertStringContainsString('Minimum order: 50', $body);
        // Rates can be kept private
        $this->bot(['share_rates' => false]);
        $this->hook('1001', '919876500001', 'azee rate');
        $this->assertStringNotContainsString('₹', $this->lastBot()->body);
    }

    public function test_price_list_pdf_order_and_samples_become_tasks(): void
    {
        $this->bot();
        $lead = $this->known();
        $this->hook('1001', '919876500001', 'hello');
        $this->hook('1001', '919876500001', 'rate list bhejo');
        $doc = collect($this->botSent())->firstWhere('type', 'document');
        $this->assertSame('price-list.pdf', $doc['document']['filename']);
        $this->assertSame('PDF', substr((string) file_get_contents(collect(\Illuminate\Support\Facades\Storage::disk('local')->files('bot'))->map(fn ($f) => \Illuminate\Support\Facades\Storage::disk('local')->path($f))->first()), 1, 3));

        $this->hook('1001', '919876500001', 'Order: 100 strip Pantop-D chahiye, Indore delivery');
        $task = Task::where('title', 'like', '%Order%')->sole();
        $this->assertSame([$this->repA->id, $lead->id, 'high'], [$task->assigned_to, $task->lead_id, $task->priority]);
        $this->assertStringContainsString('100 strip', $task->details);
        $this->assertSame(['interested', 'hot'], [$lead->fresh()->status, $lead->fresh()->temperature]);
        $this->assertStringContainsString('Amit jaldi rate, quantity aur delivery confirm karenge', $this->lastBot()->body, 'client wrote Hinglish, gets Hinglish');

        $this->hook('1001', '919876500001', 'please send COA and GMP certificate');
        $this->assertSame(1, Task::where('title', 'like', '%Documents asked%')->count());
    }

    public function test_menu_button_talk_to_person_hands_over_and_bot_stays_quiet(): void
    {
        $this->bot();
        $this->known();
        $this->hook('1001', '919876500001', 'hello');
        $this->hook('1001', '919876500001', 'Talk to a person', ['bot_human', 'Talk to a person']);
        $c = WaConversation::sole();
        $this->assertTrue($c->waiting_callback);
        $this->assertFalse($c->botMayReply());
        $this->assertStringContainsString('Amit will contact you', $this->lastBot()->body);
        $this->assertSame('bot_handoff', $this->repA->notifications()->first()->data['kind']);
        $before = WaMessage::where('type', 'bot')->count();
        $this->hook('1001', '919876500001', 'rate list?');
        $this->assertSame($before, WaMessage::where('type', 'bot')->count(), 'person handles it now');
    }

    public function test_complaint_is_handed_over_with_an_apology(): void
    {
        $this->bot();
        $this->known();
        $this->hook('1001', '919876500001', 'hello');
        $this->hook('1001', '919876500001', 'Last order was damaged, strips broken');
        $this->assertStringContainsString('sorry', $this->lastBot()->body);
        $this->assertTrue(WaConversation::sole()->urgent);
    }

    public function test_company_questions_and_answers(): void
    {
        $this->bot();
        $this->known();
        BotAnswer::create(['keywords' => 'franchise, pcd, monopoly', 'answer' => 'Yes, we give PCD franchise with monopoly rights in your district.', 'active' => true]);
        BotAnswer::create(['keywords' => 'delivery time, kitne din', 'answer' => 'Delivery in 3-5 working days.', 'active' => false]);
        $this->hook('1001', '919876500001', 'hello');
        $this->hook('1001', '919876500001', 'PCD franchise milegi kya?');
        $this->assertStringContainsString('monopoly rights', $this->lastBot()->body);
        $this->assertSame(1, BotAnswer::first()->hits);
        $this->hook('1001', '919876500001', 'delivery time kitne din?');
        $this->assertStringNotContainsString('3-5 working days', $this->lastBot()->body, 'switched-off answers are not used');
        $n = WaMessage::where('type', 'bot')->count();
        foreach (['ok thanks', '👍', 'Thank you sir', 'theek hai ji', 'धन्यवाद'] as $thanks) {
            $this->hook('1001', '919876500001', $thanks);
        }
        $this->assertSame($n, WaMessage::where('type', 'bot')->count(), 'no reply to a thank-you');
    }

    public function test_salesperson_reply_quiets_the_bot_and_it_can_be_stopped_per_chat(): void
    {
        $this->bot(['human_pause_hours' => 6]);
        $this->known();
        $this->hook('1001', '919876500001', 'hello');
        $c = WaConversation::sole();
        $this->actingAs($this->repA)->post("/inbox/{$c->id}/send", ['body' => 'Ji boliye'])->assertSessionHas('ok');
        $this->assertTrue($c->fresh()->bot_paused_until->between(now()->addHours(5), now()->addHours(7)));
        $n = WaMessage::where('type', 'bot')->count();
        $this->hook('1001', '919876500001', 'rate list?');
        $this->assertSame($n, WaMessage::where('type', 'bot')->count());

        $this->travel(7)->hours();
        $this->post("/inbox/{$c->id}/bot")->assertSessionHas('ok'); // stop here for good
        $this->assertTrue($c->fresh()->bot_paused_until->gt(now()->addYears(4)));
        $this->post("/inbox/{$c->id}/bot");
        $this->assertTrue($c->fresh()->botMayReply());
    }

    public function test_away_mode_stop_and_loop_guard(): void
    {
        $this->bot(['mode' => 'away']);
        $this->known();
        $this->hook('1001', '919876500001', 'hello');
        $this->assertSame(0, WaMessage::where('type', 'bot')->count(), 'Amit is at work');
        $this->travelTo(Carbon::parse('2026-11-12 21:00:00'));
        $this->hook('1001', '919876500001', 'hello');
        $this->assertStringContainsString('Amit', WaMessage::where('type', 'bot')->get()->pluck('body')->implode(' '));
        $this->assertSame(0, WaMessage::where('type', 'auto')->count(), 'the bot replaces the away message, never both');

        $this->hook('1001', '919876500001', 'STOP');
        $this->assertTrue(Lead::first()->whatsapp_opt_out);
        $n = WaMessage::where('type', 'bot')->count();
        $this->hook('1001', '919876500001', 'rate');
        $this->assertSame($n, WaMessage::where('type', 'bot')->count(), 'opted-out client gets nothing');
    }

    public function test_never_more_than_eight_bot_messages_an_hour(): void
    {
        $this->bot();
        $this->known();
        for ($i = 0; $i < 12; $i++) {
            $this->hook('1001', '919876500001', 'pantop rate');
        }
        $this->assertSame(8, WaMessage::where('type', 'bot')->count());
    }

    public function test_bot_answers_in_hindi_and_in_the_clients_language_with_translation(): void
    {
        $this->bot();
        $this->known();
        $this->hook('1001', '919876500001', 'नमस्ते जी');
        $this->assertStringContainsString('नमस्ते', $this->lastBot()->body);

        $fake = new class implements Provider
        {
            public function supports(string $lang): bool
            {
                return true;
            }

            public function translate(array $texts, ?string $from, string $to): array
            {
                return array_map(fn ($t) => '['.$to.'] '.$t, $texts);
            }
        };
        $this->app->bind(Translator::class, fn () => (new Translator)->using($fake));
        $this->known('919876500003', ['firm_name' => 'Murugan Pharma']);
        $this->hook('1001', '919876500003', 'வணக்கம்');
        $body = $this->lastBot()->body;
        $this->assertStringContainsString('[ta] ', $body);
        $buttons = collect($this->botSent())->last()['interactive']['action']['buttons'];
        $this->assertStringStartsWith('[ta] ', $buttons[0]['reply']['title']);
        $this->assertLessThanOrEqual(20, mb_strlen($buttons[0]['reply']['title']));
    }

    public function test_try_box_sends_and_saves_nothing_and_is_admin_only(): void
    {
        $this->actingAs($this->repA)->get('/chatbot')->assertForbidden();
        $this->actingAs($this->admin)->get('/chatbot')->assertOk()->assertSee('Try it');
        $counts = [Lead::count(), WaMessage::count(), Task::count(), WaConversation::count()];
        $this->post('/chatbot/try', ['message' => 'pantop rate kya hai'])->assertSessionHas('bot_try', fn ($t) => str_contains($t['replies'][0]['text'], '₹85.50'));
        $this->get('/chatbot')->assertSee('₹85.50')->assertSee('See the reply');
        $this->post('/chatbot/try', ['message' => 'Order 200 strips Azee 500 urgently'])->assertSessionHas('bot_try', fn ($t) => str_contains($t['replies'][0]['text'], 'confirm'));
        $this->assertSame($counts, [Lead::count(), WaMessage::count(), Task::count(), WaConversation::count()]);
        $this->assertSame([], $this->botSent());
        CompanySetting::forget();
        $this->assertNull(CompanySetting::current()->bot_config, 'try box never switches the bot on');
    }

    public function test_settings_and_answers_are_saved(): void
    {
        $this->actingAs($this->admin)->post('/chatbot', ['enabled' => 1, 'mode' => 'away', 'welcome' => 'Hi from {company}', 'human_pause_hours' => 4, 'share_rates' => 1])->assertSessionHas('ok');
        CompanySetting::forget();
        $this->assertSame(['enabled' => true, 'mode' => 'away', 'share' => true, 'list' => false, 'h' => 4],
            ['enabled' => \App\Services\WhatsApp\ChatBot::config('enabled'), 'mode' => \App\Services\WhatsApp\ChatBot::config('mode'), 'share' => \App\Services\WhatsApp\ChatBot::config('share_rates'),
                'list' => \App\Services\WhatsApp\ChatBot::config('send_price_list'), 'h' => \App\Services\WhatsApp\ChatBot::humanPauseHours()]);
        $this->post('/chatbot', ['mode' => 'never', 'human_pause_hours' => 0])->assertSessionHasErrors(['mode', 'human_pause_hours']);
        $this->post('/chatbot/answers', ['keywords' => 'gst, gst number', 'answer' => 'Our GSTIN is 23ABCDE1234F1Z5.'])->assertSessionHas('ok');
        $a = BotAnswer::sole();
        $this->put("/chatbot/answers/{$a->id}", ['keywords' => 'gst', 'answer' => 'GSTIN 23ABCDE1234F1Z5'])->assertSessionHas('ok');
        $this->assertFalse($a->fresh()->active);
        $this->delete("/chatbot/answers/{$a->id}")->assertSessionHas('ok');
        $this->assertSame(0, BotAnswer::count());
    }

    public function test_every_bot_sentence_is_written_in_hindi_and_hinglish(): void
    {
        $src = file_get_contents(app_path('Services/WhatsApp/ChatBot.php'));
        preg_match_all("/->t\\('((?:[^'\\\\]|\\\\.)+)'/", $src, $m);
        preg_match('/MENU = \[(.*?)\];/', $src, $line);
        preg_match_all("/'(bot_[a-z]+)' => '([^']+)'/", $line[1], $menu);
        $keys = array_unique(array_merge(array_map('stripslashes', $m[1]), $menu[2], ['Composition', 'Packing', 'Form', 'Our team', 'from :time']));
        $this->assertGreaterThan(15, count($keys));
        foreach (['hi', 'hing'] as $lang) {
            $json = json_decode(file_get_contents(lang_path($lang.'.json')), true);
            $this->assertSame([], array_values(array_diff($keys, array_keys($json))), $lang);
        }
    }

    // ---------- team inbox ----------

    public function test_company_number_chats_go_to_the_clients_salesperson_and_can_be_given_to_others(): void
    {
        $this->known('919876500004'); // Amit's client
        $this->hook('1009', '919876500004', 'hello');
        $c = WaConversation::sole();
        $this->assertSame($this->repA->id, $c->assigned_to);
        $this->actingAs($this->repA)->get("/inbox/{$c->id}")->assertOk();
        $this->actingAs($this->repB)->get("/inbox/{$c->id}")->assertNotFound();
        $this->actingAs($this->otherTeamRep)->get('/inbox')->assertDontSee('Sharma Medicos');

        // Rep cannot hand it over; manager can
        $this->actingAs($this->repA)->post("/inbox/{$c->id}/assign", ['assigned_to' => $this->repB->id])->assertNotFound();
        $this->actingAs($this->manager)->post("/inbox/{$c->id}/assign", ['assigned_to' => $this->otherTeamRep->id])->assertSessionHasErrors('assigned_to');
        $this->post("/inbox/{$c->id}/assign", ['assigned_to' => $this->repB->id])->assertSessionHas('ok');
        $this->assertSame('chat_assigned', $this->repB->notifications()->first()->data['kind']);
        $this->actingAs($this->repB)->get("/inbox/{$c->id}")->assertOk()->assertSee('Handled by');
        $this->actingAs($this->repA)->get("/inbox/{$c->id}")->assertNotFound();
        $this->actingAs($this->manager)->post("/inbox/{$c->id}/assign", ['assigned_to' => 0])->assertSessionHas('ok');
        $this->assertNull($c->fresh()->assigned_to);
        $this->get('/inbox?show=unassigned')->assertSee('Sharma Medicos');
        $this->get('/inbox?show=mine')->assertDontSee('Sharma Medicos');
    }

    public function test_team_notes_are_never_sent_and_closed_chats_reopen_when_the_client_writes(): void
    {
        $this->known();
        $this->hook('1001', '919876500001', 'hello');
        $c = WaConversation::sole();
        $sent = count($this->botSent());
        $this->actingAs($this->repA)->post("/inbox/{$c->id}/note", ['note' => 'Wants 10% scheme, ask manager'])->assertSessionHas('ok');
        $this->assertSame($sent, count($this->botSent()), 'note never goes to WhatsApp');
        $this->get("/inbox/{$c->id}")->assertSee('Team note: the client does not see this')->assertSee('Wants 10% scheme');
        $this->assertNotSame('Wants 10% scheme, ask manager', DB::table('wa_messages')->where('direction', 'note')->value('body'), 'encrypted');
        $this->actingAs($this->repB)->post("/inbox/{$c->id}/note", ['note' => 'x'])->assertNotFound();

        $this->actingAs($this->repA)->post("/inbox/{$c->id}/close")->assertSessionHas('ok');
        $this->assertSame('closed', $c->fresh()->status);
        $this->get('/inbox')->assertDontSee('Sharma Medicos');
        $this->get('/inbox?show=closed')->assertSee('Sharma Medicos');
        $this->hook('1001', '919876500001', 'one more thing');
        $this->assertSame('open', $c->fresh()->status);
    }

    // ---------- broadcast ----------

    private function campaign(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/campaigns', $extra + ['name' => 'Winter scheme', 'whatsapp_number_id' => $this->companyNo->id,
            'template_name' => 'winter_scheme', 'template_language' => 'en', 'var' => ['contact_name']]);
    }

    public function test_scheduled_broadcast_starts_on_time_from_the_minute_task(): void
    {
        $this->known('919876500011');
        $this->campaign(['scheduled_at' => '2026-11-13 10:00'])->assertSessionHas('ok');
        $camp = Campaign::sole();
        $this->assertSame('scheduled', $camp->status);
        Artisan::call('campaigns:due');
        $this->assertSame([], $this->botSent());
        $this->travelTo(Carbon::parse('2026-11-13 10:01:00'));
        Artisan::call('campaigns:due');
        $this->assertSame('done', $camp->fresh()->status);
        $this->assertSame('winter_scheme', $this->botSent()[0]['template']['name']);
        $this->campaign(['scheduled_at' => '2026-11-01 10:00'])->assertSessionHasErrors('scheduled_at');
    }

    public function test_daily_limit_pause_resume_cancel_and_retry(): void
    {
        foreach (range(21, 25) as $i) {
            $this->known('9198765000'.$i, ['firm_name' => 'Firm '.$i]);
        }
        CompanySetting::current()->update(['whatsapp_config' => CompanySetting::current()->whatsapp_config + ['daily_limit' => 3]]);
        CompanySetting::forget();
        $this->campaign()->assertSessionHas('ok');
        $camp = Campaign::sole();
        $this->assertSame(['limit', 3, 2], [$camp->fresh()->status, $camp->recipients()->where('status', 'sent')->count(), $camp->recipients()->where('status', 'pending')->count()]);
        $this->get("/campaigns/{$camp->id}")->assertSee('limit is reached');
        Artisan::call('campaigns:due');
        $this->assertSame(2, $camp->recipients()->where('status', 'pending')->count(), 'waits while the limit is used up');
        $this->post("/campaigns/{$camp->id}/pause")->assertSessionHas('ok');
        $this->travel(25)->hours();
        Artisan::call('campaigns:due');
        $this->assertSame('paused', $camp->fresh()->status);
        $this->post("/campaigns/{$camp->id}/resume")->assertSessionHas('ok');
        $this->assertSame('done', $camp->fresh()->status);
        $this->assertSame(5, $camp->recipients()->where('status', 'sent')->count());
        $this->post("/campaigns/{$camp->id}/cancel")->assertSessionHasErrors('campaign');

        // Failed ones can be sent again
        $camp->recipients()->limit(2)->update(['status' => 'failed', 'error' => 'x']);
        $this->post("/campaigns/{$camp->id}/retry")->assertSessionHas('ok');
        $this->assertSame(0, $camp->recipients()->where('status', 'failed')->count());
        $this->actingAs($this->repA)->post("/campaigns/{$camp->id}/pause")->assertForbidden();
    }

    public function test_cancel_stops_clients_not_reached(): void
    {
        $this->known('919876500031');
        $this->known('919876500032', ['firm_name' => 'B']);
        $this->campaign(['scheduled_at' => '2026-11-20 10:00']);
        $camp = Campaign::sole();
        $this->post("/campaigns/{$camp->id}/cancel")->assertSessionHas('ok');
        $this->assertSame(['cancelled', 2], [$camp->fresh()->status, $camp->recipients()->where('status', 'skipped')->count()]);
        $this->get("/campaigns/{$camp->id}")->assertOk()->assertSee('Was to start 20 Nov 2026');
        $this->travelTo(Carbon::parse('2026-11-21 10:00'));
        Artisan::call('campaigns:due');
        $this->assertSame([], $this->botSent());
    }

    public function test_photo_header_from_catalogue_and_audience_filters(): void
    {
        $this->known('919876500041', ['firm_name' => 'Tag Firm', 'tags' => ['Diwali list'], 'city' => 'Pune', 'language' => 'mr']);
        $this->known('919876500042', ['firm_name' => 'Other Firm', 'city' => 'Indore', 'language' => 'hi']);
        $this->actingAs($this->admin)->get('/campaigns/new?tag=Diwali+list&preview=1')->assertSee('1 clients match');
        $this->get('/campaigns/new?city=pune&preview=1')->assertSee('1 clients match');
        $this->get('/campaigns/new?language=hi&preview=1')->assertSee('1 clients match');
        \Illuminate\Support\Facades\URL::forceScheme('https'); // live CRMs run on https; WhatsApp fetches only https
        \Illuminate\Support\Facades\Storage::disk('local')->put('catalogue/offer.jpg', 'jpg');
        $file = ProductFile::create(['title' => 'Offer', 'path' => 'catalogue/offer.jpg', 'original_name' => 'offer.jpg', 'extension' => 'jpg', 'mime' => 'image/jpeg', 'size' => 3, 'version' => 1, 'uploaded_by' => $this->admin->id]);
        $this->campaign(['tag' => 'Diwali list', 'header_file_id' => $file->id])->assertSessionHas('ok');
        $tpl = $this->botSent()[0]['template'];
        $this->assertSame('header', $tpl['components'][0]['type']);
        $this->assertSame(route('catalogue.shared', $file->fresh()->share_token), $tpl['components'][0]['parameters'][0]['image']['link']);
        $this->assertSame(1, Campaign::sole()->total);
        $this->get(route('catalogue.shared', $file->fresh()->share_token))->assertOk();
        $this->campaign(['header_url' => 'https://example.com/offer.exe'])->assertSessionHasErrors('header_url');
        $this->campaign(['header_url' => 'http://example.com/offer.jpg'])->assertSessionHasErrors('header_url');
    }

    public function test_replies_and_orders_after_a_broadcast_are_counted(): void
    {
        $lead = $this->known('919876500051');
        $this->known('919876500052', ['firm_name' => 'Silent Firm']);
        $this->campaign()->assertSessionHas('ok');
        $camp = Campaign::sole();
        $this->travel(2)->hours();
        $this->hook('1009', '919876500051', 'Interested, send rates');
        $this->assertNotNull(CampaignRecipient::where('lead_id', $lead->id)->value('replied_at'));
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $lead->id, 'amount' => 45000, 'sale_date' => today(), 'status' => 'confirmed']);
        $this->actingAs($this->admin)->get("/campaigns/{$camp->id}")->assertOk()->assertSee('₹45,000')->assertSee('50%');
        $this->get("/campaigns/{$camp->id}?show=replied")->assertSee('Sharma Medicos')->assertDontSee('Silent Firm');
        $this->get('/campaigns')->assertOk()->assertSee('Finished');
    }
}
