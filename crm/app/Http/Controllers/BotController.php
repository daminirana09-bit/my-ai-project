<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BotAnswer;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\WhatsApp\ChatBot;
use App\Services\WhatsApp\Client;
use App\Services\WhatsApp\IntentDetector;
use App\Services\WhatsApp\LanguageDetector;
use App\Services\WhatsApp\SimulatedClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Chatbot settings, its questions & answers, and a safe "try it" box (admins). */
class BotController extends Controller
{
    public function index()
    {
        $c = CompanySetting::current();

        return view('chatbot', [
            'cfg' => fn (string $k) => ChatBot::config($k),
            'answers' => BotAnswer::orderByDesc('active')->orderByDesc('hits')->orderBy('id')->get(),
            'languages' => LanguageDetector::NAMES,
            'stats' => [
                'chats' => WaConversation::whereNotNull('bot_replied_at')->where('bot_replied_at', '>=', now()->subDays(30))->count(),
                'replies' => WaMessage::where('type', 'bot')->where('sent_at', '>=', now()->subDays(30))->count(),
                'handoffs' => WaMessage::where('type', 'bot')->where('intent', 'handoff')->where('sent_at', '>=', now()->subDays(30))->count(),
            ],
            'connected' => app(Client::class)->configured(),
            'translator' => app(\App\Services\Translation\Translator::class)->enabled(),
            'company' => $c,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(ChatBot::MODES))],
            'welcome' => ['nullable', 'string', 'max:600'],
            'human_pause_hours' => ['required', 'integer', 'min:1', 'max:72'],
        ]);
        $cfg = [
            'enabled' => $request->boolean('enabled'),
            'mode' => $data['mode'],
            'welcome' => trim((string) ($data['welcome'] ?? '')) ?: null,
            'share_rates' => $request->boolean('share_rates'),
            'send_price_list' => $request->boolean('send_price_list'),
            'ask_details' => $request->boolean('ask_details'),
            'human_pause_hours' => (int) $data['human_pause_hours'],
        ];
        CompanySetting::current()->update(['bot_config' => $cfg]);
        CompanySetting::forget();
        AuditLog::record('bot.settings', null, ['enabled' => $cfg['enabled'], 'mode' => $cfg['mode']]);

        return back()->with('ok', $cfg['enabled'] ? __('Chatbot is on. Try it below.') : __('Chatbot settings saved. It is switched off.'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        BotAnswer::create($data + ['created_by' => $request->user()->id, 'active' => true]);
        AuditLog::record('bot.answer.add');

        return back()->with('ok', __('Answer added.'));
    }

    public function updateAnswer(Request $request, BotAnswer $answer)
    {
        $answer->update($this->validated($request) + ['active' => $request->boolean('active')]);
        AuditLog::record('bot.answer.update', "bot_answer:{$answer->id}");

        return back()->with('ok', __('Answer saved.'));
    }

    public function destroy(BotAnswer $answer)
    {
        $answer->delete();
        AuditLog::record('bot.answer.delete', "bot_answer:{$answer->id}");

        return back()->with('ok', __('Answer deleted.'));
    }

    /** Runs the bot on a made-up chat inside a transaction that is undone: nothing is saved or sent. */
    public function test(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500'], 'lang' => ['nullable', Rule::in(array_keys(LanguageDetector::NAMES))]]);
        $fake = new SimulatedClient;
        $co = CompanySetting::current();
        $saved = $co->bot_config;
        $lang = ($data['lang'] ?? null) ?: app(LanguageDetector::class)->detect($data['message']);
        DB::beginTransaction();
        try {
            $co->bot_config = array_merge($saved ?? [], ['enabled' => true, 'mode' => 'always']); // only in memory, for this try
            $number = WhatsappNumber::create(['label' => 'Test', 'display_phone' => '+91 00000 00000', 'phone_number_id' => 'test-'.uniqid(), 'active' => true]);
            $lead = Lead::create(['firm_name' => 'Test Client', 'contact_name' => 'Ramesh', 'phone' => '9000000000', 'city' => 'Test city', 'status' => 'new', 'created_via' => 'manual', 'owner_id' => $request->user()->id]);
            $conv = WaConversation::create(['whatsapp_number_id' => $number->id, 'wa_id_hash' => 'test-'.uniqid(), 'wa_id' => '919000000000', 'lead_id' => $lead->id,
                'assigned_to' => $request->user()->id, 'language' => $lang, 'last_inbound_at' => now(), 'last_message_at' => now()]);
            WaMessage::create(['wa_conversation_id' => $conv->id, 'direction' => 'out', 'body' => '-', 'type' => 'bot', 'intent' => 'welcome', 'status' => 'sent', 'sent_at' => now()->subMinutes(5)]);
            $msg = WaMessage::create(['wa_conversation_id' => $conv->id, 'direction' => 'in', 'body' => $data['message'], 'type' => 'text', 'status' => 'received',
                'language' => $lang, 'intent' => app(IntentDetector::class)->detect($data['message']), 'sent_at' => now()]);
            app()->instance(Client::class, $fake);
            app(ChatBot::class)->answer($conv->id, $msg->id, null);
        } finally {
            DB::rollBack();
            app()->forgetInstance(Client::class);
            $co->bot_config = $saved;
            CompanySetting::forget();
        }

        return back()->withInput()->with('bot_try', ['message' => $data['message'], 'lang' => $lang, 'replies' => $fake->sent]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'keywords' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:1500'],
        ]);
    }
}
