<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\Translation\BhashiniProvider;
use App\Services\Translation\GoogleProvider;
use App\Services\Translation\MicrosoftProvider;
use App\Services\Translation\Provider;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\Translator;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Talking to every client in their own language. The translation services are faked. */
class ChatTranslationTest extends CrmTestCase
{
    private WaConversation $conv;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        CompanySetting::current()->update(['whatsapp_config' => ['access_token' => 'tok', 'app_secret' => 'sec', 'verify_token' => 'vt', 'api_version' => 'v23.0']]);
        CompanySetting::forget();
        $no = WhatsappNumber::create(['label' => 'Amit', 'display_phone' => '+91 90000 00001', 'phone_number_id' => '100000000000001', 'user_id' => $this->repA->id, 'active' => true]);
        $lead = Lead::create(['firm_name' => 'Murugan Pharma', 'contact_name' => 'Murugan', 'phone' => '9876500001', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->conv = WaConversation::create(['whatsapp_number_id' => $no->id, 'wa_id_hash' => Lead::hashPhone('919876500001'), 'wa_id' => '919876500001',
            'lead_id' => $lead->id, 'language' => 'ta', 'last_inbound_at' => now()->subMinutes(5), 'last_message_at' => now()->subMinutes(5)]);
    }

    private function bhashini(): void
    {
        CompanySetting::current()->update(['translation_config' => ['provider' => 'bhashini', 'auto' => true, 'bhashini_user' => 'u-1', 'bhashini_key' => 'k-1']]);
        CompanySetting::forget();
    }

    /** Fake Bhashini: echoes each line as "[to] line". */
    private function fakeBhashini(?callable $map = null): void
    {
        Http::fake([
            BhashiniProvider::CONFIG_URL => Http::response(['pipelineResponseConfig' => [['taskType' => 'translation', 'config' => [['serviceId' => 'ai4bharat/indictrans-v2']]]],
                'pipelineInferenceAPIEndPoint' => ['callbackUrl' => 'https://dhruva-api.bhashini.gov.in/services/inference/pipeline', 'inferenceApiKey' => ['name' => 'Authorization', 'value' => 'inf-key']]]),
            'dhruva-api.bhashini.gov.in/*' => function (HttpRequest $r) use ($map) {
                $to = $r['pipelineTasks'][0]['config']['language']['targetLanguage'];
                if ($r['pipelineTasks'][0]['taskType'] === 'transliteration') {
                    return Http::response(['pipelineResponse' => [['taskType' => 'transliteration', 'output' => array_map(fn ($i) => ['source' => $i['source'],
                        'target' => [['kal bhej denge' => 'कल भेज देंगे'][$i['source']] ?? 'देव '.$i['source']]], $r['inputData']['input'])]]]);
                }

                return Http::response(['pipelineResponse' => [['taskType' => 'translation', 'output' => array_map(fn ($i) => ['source' => $i['source'],
                    'target' => $map ? $map($i['source'], $to) : '['.$to.'] '.$i['source']], $r['inputData']['input'])]]]);
            },
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT1']]]),
        ]);
    }

    public function test_bhashini_request_format_and_config_kept_for_the_day(): void
    {
        $this->bhashini();
        $this->fakeBhashini();
        $tr = app(Translator::class);
        $this->assertSame('[gom] Order sent', $tr->translate('Order sent', 'en', 'kok')['text']);
        $this->assertSame('[gom] Thank you', $tr->translate('Thank you', 'en', 'kok')['text']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === BhashiniProvider::CONFIG_URL && $r->hasHeader('userID', 'u-1') && $r->hasHeader('ulcaApiKey', 'k-1')
            && $r['pipelineRequestConfig']['pipelineId'] === BhashiniProvider::DEFAULT_PIPELINE && $r['pipelineTasks'][0]['config']['language'] === ['sourceLanguage' => 'en', 'targetLanguage' => 'gom']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'dhruva-api') && $r->hasHeader('Authorization', 'inf-key') && $r['pipelineTasks'][0]['config']['serviceId'] === 'ai4bharat/indictrans-v2');
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($p) => $p[0]->url() === BhashiniProvider::CONFIG_URL)->count(), 'config asked once');
    }

    public function test_bhashini_refuses_an_unexpected_address_and_romanised_input(): void
    {
        Http::fake([BhashiniProvider::CONFIG_URL => Http::response(['pipelineResponseConfig' => [['config' => [['serviceId' => 's']]]],
            'pipelineInferenceAPIEndPoint' => ['callbackUrl' => 'https://evil.example/collect', 'inferenceApiKey' => ['name' => 'Authorization', 'value' => 'x']]])]);
        $p = new BhashiniProvider('u', 'k');
        try {
            $p->translate(['Hello'], 'en', 'ta');
            $this->fail('should refuse');
        } catch (TranslationFailed $e) {
            $this->assertStringContainsString('unexpected address', $e->getMessage());
        }
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'evil.example'));
        $this->expectException(TranslationFailed::class);
        $p->translate(['kya haal hai'], null, 'ta');
    }

    public function test_google_and_microsoft_request_formats(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => Http::response(['data' => ['translations' => [['translatedText' => 'நன்றி &amp; வணக்கம்']]]]),
            'api.cognitive.microsofttranslator.com/*' => Http::response([['translations' => [['text' => 'ধন্যবাদ', 'to' => 'bn']]]]),
        ]);
        $this->assertSame(['நன்றி & வணக்கம்'], (new GoogleProvider('gk'))->translate(['Thanks & hello'], null, 'ta'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'key=gk') && $r['target'] === 'ta' && ! isset($r['source']) && $r['format'] === 'text');
        // Tamil typed in English letters: the service is left to work out the language
        CompanySetting::current()->update(['translation_config' => ['provider' => 'google', 'google_key' => 'gk']]);
        CompanySetting::forget();
        app(Translator::class)->translate('Vilai pattiyal anuppungal', 'ta', 'en');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'googleapis') && $r['q'] === ['Vilai pattiyal anuppungal'] && ! isset($r['source']));
        $this->assertSame(['ধন্যবাদ'], (new MicrosoftProvider('mk', 'centralindia'))->translate(['Thanks'], 'en', 'bn'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'to=bn') && str_contains($r->url(), 'from=en') && $r->hasHeader('Ocp-Apim-Subscription-Key', 'mk')
            && $r->hasHeader('Ocp-Apim-Subscription-Region', 'centralindia') && $r->data() === [['Text' => 'Thanks']]);
    }

    public function test_phone_numbers_emails_and_links_never_leave_and_come_back_exact(): void
    {
        $seen = [];
        $p = new class($seen) implements Provider
        {
            public function __construct(public array &$seen) {}

            public function supports(string $lang): bool
            {
                return true;
            }

            public function translate(array $texts, ?string $from, string $to): array
            {
                array_push($this->seen, ...$texts);

                return array_map(fn ($t) => 'T('.$t.')', $texts);
            }
        };
        $tr = app(Translator::class)->using($p);
        $out = $tr->translate("Call me on +91 98765 43210 or mail ravi.k@example.in\nRate list: https://x.in/list?a=1 thanks", 'en', 'ta')['text'];
        $this->assertSame("T(Call me on) +91 98765 43210 T(or mail) ravi.k@example.in\nT(Rate list:) https://x.in/list?a=1 T(thanks)", $out);
        foreach ($seen as $line) {
            $this->assertDoesNotMatchRegularExpression('/98765|@|https?:/', $line);
        }
        // The same line again: from the saved translations, not sent again
        $count = count($seen);
        $tr->translate('Call me on +91 90000 11111', 'en', 'ta');
        $this->assertCount($count, $seen);
        $this->assertStringNotContainsString('T(Call me on)', (string) DB::table('translation_cache')->value('text'), 'kept encrypted');
    }

    public function test_hinglish_from_the_salesperson_is_written_in_hindi_letters_then_translated(): void
    {
        $this->bhashini();
        $this->fakeBhashini();
        $this->assertSame('[ta] कल भेज देंगे', app(Translator::class)->translate('kal bhej denge', 'hing', 'ta')['text']);
        $tasks = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn ($r) => str_contains($r->url(), 'dhruva-api'))
            ->map(fn ($r) => $r['pipelineTasks'][0]['taskType'].':'.implode('>', $r['pipelineTasks'][0]['config']['language']))->values()->all();
        $this->assertSame(['transliteration:en>hi', 'translation:hi>ta'], $tasks);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'dhruva-api') && $r['pipelineTasks'][0]['taskType'] === 'transliteration'
            && $r['pipelineTasks'][0]['config']['isSentence'] === true && $r['pipelineTasks'][0]['config']['numSuggestions'] === 1);
        // Tamil typed in English letters, read by an English reader: Tamil script first, then English
        $this->assertSame('[en] देव Vanakkam', app(Translator::class)->translate('Vanakkam', 'ta', 'en')['text']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'dhruva-api') && $r['pipelineTasks'][0]['config']['language'] === ['sourceLanguage' => 'en', 'targetLanguage' => 'ta']);
    }

    public function test_changed_numbers_are_flagged_and_native_digits_are_fine(): void
    {
        $this->bhashini();
        $this->fakeBhashini(fn ($src, $to) => $to === 'bn' ? '১০০০০ স্ট্রিপ' : str_replace('10000', '1000', $src));
        $tr = app(Translator::class);
        $this->assertSame([], $tr->translate('10000 strips', 'en', 'bn')['warnings']);
        $this->assertNotEmpty($tr->translate('Need 10000 strips', 'en', 'ta')['warnings']);
    }

    public function test_hinglish_clients_get_roman_letters(): void
    {
        $tr = app(Translator::class);
        $this->assertSame('aapka ordar kal bhej denge', $tr->translate('आपका ऑर्डर कल भेज देंगे', 'hi', 'hing')['text']);
        $this->bhashini();
        $this->fakeBhashini(fn () => 'धन्यवाद, हम कल भेजेंगे');
        $this->assertSame('dhanyavaad, hum kal bhejenge', app(Translator::class)->translate('Thanks, we will send tomorrow', 'en', 'hing')['text']);
    }

    public function test_inbox_shows_meaning_and_sends_in_the_clients_language_after_a_meaning_check(): void
    {
        $this->bhashini();
        $this->fakeBhashini(fn ($src, $to) => match ($to) {
            'en' => $src === 'விலை பட்டியல் அனுப்புங்கள்' ? 'Please send the price list' : 'We will send the price list today',
            'ta' => 'இன்று விலை பட்டியலை அனுப்புகிறோம்',
            default => $src,
        });
        $in = WaMessage::create(['wa_conversation_id' => $this->conv->id, 'direction' => 'in', 'body' => 'விலை பட்டியல் அனுப்புங்கள்', 'language' => 'ta', 'type' => 'text', 'status' => 'received', 'sent_at' => now()]);

        $this->actingAs($this->repA)->get("/inbox/{$this->conv->id}")->assertOk()->assertSee('Please send the price list')->assertSee('Meaning (English)');
        $this->assertSame('en', $in->fresh()->translation_lang);

        $this->post("/inbox/{$this->conv->id}/draft", ['draft' => 'We will send the price list today'])->assertSessionHas('draft', fn ($d) => $d['text'] === 'இன்று விலை பட்டியலை அனுப்புகிறோம்'
            && $d['check'] === 'We will send the price list today' && $d['from'] === 'en');
        $this->get("/inbox/{$this->conv->id}")->assertSee('Meaning check')->assertSee('இன்று விலை பட்டியலை அனுப்புகிறோம்');

        $this->post("/inbox/{$this->conv->id}/send", ['body' => 'இன்று விலை பட்டியலை அனுப்புகிறோம்', 'original' => 'We will send the price list today', 'original_lang' => 'en'])->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages') && $r['text']['body'] === 'இன்று விலை பட்டியலை அனுப்புகிறோம்');
        $out = WaMessage::where('direction', 'out')->sole();
        $this->assertSame(['இன்று விலை பட்டியலை அனுப்புகிறோம்', 'We will send the price list today'], [$out->body, $out->translation]);

        // "Send as written" still works
        $this->post("/inbox/{$this->conv->id}/send", ['draft' => 'Thanks'])->assertSessionHas('ok');
        $this->assertSame(2, WaMessage::where('direction', 'out')->count());
    }

    public function test_salesperson_can_fix_the_client_language_and_others_cannot_touch_the_chat(): void
    {
        $this->bhashini();
        $this->fakeBhashini();
        $this->actingAs($this->repB)->post("/inbox/{$this->conv->id}/language", ['language' => 'ml'])->assertNotFound();
        $this->actingAs($this->repB)->post("/inbox/{$this->conv->id}/draft", ['draft' => 'Hi'])->assertNotFound();
        $m = WaMessage::create(['wa_conversation_id' => $this->conv->id, 'direction' => 'in', 'body' => 'x', 'language' => 'ta', 'type' => 'text', 'status' => 'received', 'sent_at' => now()]);
        $this->actingAs($this->repB)->post("/inbox/message/{$m->id}/translate")->assertNotFound();

        $en = WaMessage::create(['wa_conversation_id' => $this->conv->id, 'direction' => 'in', 'body' => 'ok thanks', 'language' => 'en', 'type' => 'text', 'status' => 'received', 'sent_at' => now()]);
        $this->actingAs($this->repA)->post("/inbox/{$this->conv->id}/language", ['language' => 'ml'])->assertSessionHas('ok');
        $this->assertSame('ml', $this->conv->fresh()->language);
        $this->assertSame(['ml', 'en'], [$m->fresh()->language, $en->fresh()->language]);
        $this->assertSame('ml', Lead::find($this->conv->lead_id)->language);
        $this->post("/inbox/{$this->conv->id}/language", ['language' => 'xx'])->assertSessionHasErrors('language');
    }

    public function test_when_switched_off_nothing_is_sent_and_the_normal_reply_box_shows(): void
    {
        $this->actingAs($this->repA)->get("/inbox/{$this->conv->id}")->assertOk()->assertDontSee('Meaning check')->assertSee('Type a reply');
        $this->post("/inbox/{$this->conv->id}/draft", ['draft' => 'Hello'])->assertSessionHasErrors('draft');
        Http::assertNothingSent();
    }

    public function test_settings_keep_keys_secret_and_test_button_works(): void
    {
        $this->actingAs($this->repA)->post('/whatsapp/translation', ['provider' => 'bhashini'])->assertForbidden();
        $this->actingAs($this->admin)->post('/whatsapp/translation', ['provider' => 'bhashini', 'auto' => 1, 'bhashini_user' => 'u-1', 'bhashini_key' => 'SECRET-ULCA-KEY'])->assertSessionHas('ok');
        $this->assertStringNotContainsString('SECRET-ULCA-KEY', (string) DB::table('company_settings')->value('translation_config'));
        $this->get('/whatsapp')->assertOk()->assertDontSee('SECRET-ULCA-KEY')->assertSee('Saved. Leave empty to keep.');
        $this->post('/whatsapp/translation', ['provider' => 'bhashini', 'bhashini_user' => 'u-1', 'bhashini_key' => '']);
        CompanySetting::forget();
        $this->assertSame('SECRET-ULCA-KEY', CompanySetting::current()->tr('bhashini_key'));
        $this->fakeBhashini();
        $this->post('/whatsapp/translation/test', ['lang' => 'ta'])->assertSessionHas('ok', fn ($m) => str_contains($m, '[ta]'));
    }

    public function test_ready_replies_for_languages_without_one_are_machine_translated(): void
    {
        $this->bhashini();
        $this->fakeBhashini();
        $this->conv->update(['language' => 'kok', 'last_intent' => 'price']);
        $s = app(\App\Services\WhatsApp\ReplySuggester::class)->suggest($this->conv->fresh(), 'en');
        $this->assertTrue($s->first()->machine);
        $this->assertStringStartsWith('[gom] ', $s->first()->text);
        $this->actingAs($this->repA)->get("/inbox/{$this->conv->id}")->assertSee('Machine translated');
    }
}
