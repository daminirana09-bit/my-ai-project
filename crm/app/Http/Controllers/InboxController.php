<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\ChatAssigned;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Services\WhatsApp\Client;
use App\Services\WhatsApp\LanguageDetector;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\Translator;
use App\Services\WhatsApp\ReplySuggester;
use Illuminate\Http\Request;

/** WhatsApp chats of the numbers this person may see, with ready replies in the client's language. */
class InboxController extends Controller
{
    public const FILTERS = ['mine', 'unassigned', 'unread', 'callback', 'urgent', 'bot', 'closed'];

    public function index(Request $request, ?WaConversation $conversation = null)
    {
        $me = $request->user();
        $filter = $request->query('show');
        $filter = in_array($filter, self::FILTERS, true) ? $filter : null;
        $list = WaConversation::visibleTo($me)->with(['lead:id,firm_name,owner_id', 'number:id,label,user_id', 'assignee:id,name'])
            ->when($filter === 'closed', fn ($q) => $q->where('status', 'closed'), fn ($q) => $q->where('status', '!=', 'closed'))
            ->when($filter === 'mine', fn ($q) => $q->where(fn ($w) => $w->where('assigned_to', $me->id)
                ->orWhere(fn ($x) => $x->whereNull('assigned_to')->whereIn('whatsapp_number_id', \App\Models\WhatsappNumber::where('user_id', $me->id)->select('id')))))
            ->when($filter === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when($filter === 'unread', fn ($q) => $q->where('unread', '>', 0))
            ->when($filter === 'callback', fn ($q) => $q->where('waiting_callback', true))
            ->when($filter === 'urgent', fn ($q) => $q->where('urgent', true))
            ->when($filter === 'bot', fn ($q) => $q->whereNotNull('bot_replied_at')->where('bot_replied_at', '>=', now()->subDay()))
            ->orderByDesc('last_message_at')->limit(100)->get();

        $current = null;
        $suggestions = collect();
        if ($conversation) {
            abort_unless($conversation->isVisibleTo($me), 404);
            $conversation->load(['messages.user:id,name', 'lead.owner:id,name', 'number.user', 'assignee:id,name']);
            if ($conversation->unread) {
                $conversation->forceFill(['unread' => 0])->save();
            }
            $current = $conversation;
            $suggestions = app(ReplySuggester::class)->suggest($conversation, app()->getLocale());
            $this->autoTranslate($conversation, $me);
        }
        $tr = app(Translator::class);

        return view('inbox', ['list' => $list, 'current' => $current,
            'team' => $me->canManage() ? User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']) : collect(), 'suggestions' => $suggestions, 'filter' => $filter, 'languages' => LanguageDetector::NAMES,
            'translator' => $tr->enabled(), 'readLang' => Translator::readerLang($me)]);
    }

    /** New client messages, shown with their meaning in the salesperson's language (last 15, once each). */
    private function autoTranslate(WaConversation $c, $me): void
    {
        $tr = app(Translator::class);
        if (! $tr->auto()) {
            return;
        }
        $read = Translator::readerLang($me);
        $todo = $c->messages->where('direction', 'in')->filter(fn ($m) => ($m->translation === null || ! Translator::understands($read, $m->translation_lang))
            && ! Translator::understands($read, $m->language ?: $c->language))->slice(-15);
        foreach ($todo as $m) {
            try {
                $r = $tr->translate((string) $m->body, $m->language ?: $c->language, $read);
                $m->forceFill(['translation' => $r['text'], 'translation_lang' => $read])->save();
            } catch (TranslationFailed $e) {
                session()->now('warn', __('Translation: :e', ['e' => $e->getMessage()]));

                return; // try again next time the chat is opened
            }
        }
    }

    /** "Translate" under one client message. */
    public function translateMessage(Request $request, WaMessage $message)
    {
        $me = $request->user();
        abort_unless($message->conversation?->isVisibleTo($me), 404);
        $read = Translator::readerLang($me);
        try {
            $r = app(Translator::class)->translate((string) $message->body, $message->language ?: $message->conversation->language, $read);
        } catch (TranslationFailed $e) {
            return back()->withErrors(['body' => __('Translation: :e', ['e' => $e->getMessage()])]);
        }
        $message->forceFill(['translation' => $r['text'], 'translation_lang' => $read])->save();

        return back();
    }

    /**
     * The salesperson writes in their own language; the CRM shows the message in the client's
     * language (editable) and translates it back, so the meaning can be checked before sending.
     */
    public function draft(Request $request, WaConversation $conversation)
    {
        $me = $request->user();
        abort_unless($conversation->isVisibleTo($me), 404);
        $data = $request->validate(['draft' => ['required', 'string', 'max:2000']]);
        $from = app(LanguageDetector::class)->detect($data['draft']);
        $to = $conversation->language ?: 'en';
        $tr = app(Translator::class);
        try {
            $out = $tr->translate($data['draft'], $from, $to);
            $check = $tr->translate($out['text'], $to, in_array($from, ['en', 'hi', 'hing'], true) ? $from : Translator::readerLang($me));
        } catch (TranslationFailed $e) {
            return back()->withErrors(['draft' => __('Translation: :e', ['e' => $e->getMessage()])])->withInput();
        }

        return back()->with('draft', ['original' => $data['draft'], 'from' => $from, 'text' => $out['text'], 'check' => $check['text'], 'warnings' => $out['warnings']]);
    }

    /** The salesperson corrects the client's language when it was guessed wrong. */
    public function language(Request $request, WaConversation $conversation)
    {
        abort_unless($conversation->isVisibleTo($request->user()), 404);
        $lang = $request->validate(['language' => ['required', 'in:'.implode(',', array_keys(LanguageDetector::NAMES))]])['language'];
        $conversation->forceFill(['language' => $lang])->save();
        if ($conversation->lead_id) {
            Lead::whereKey($conversation->lead_id)->update(['language' => $lang]);
        }
        // The salesperson knows better than the detector: their earlier messages are in this language too (English ones stay English).
        WaMessage::where('wa_conversation_id', $conversation->id)->where('direction', 'in')
            ->where(fn ($q) => $q->whereNull('language')->orWhere('language', '!=', 'en'))->update(['language' => $lang]);
        WaMessage::where('wa_conversation_id', $conversation->id)->where('direction', 'in')->update(['translation' => null, 'translation_lang' => null]);
        AuditLog::record('inbox.language', "conversation:{$conversation->id}", ['language' => $lang]);

        return back()->with('ok', __('Client language set to :lang.', ['lang' => LanguageDetector::NAMES[$lang][0]]));
    }

    public function send(Request $request, WaConversation $conversation, Client $client)
    {
        $me = $request->user();
        abort_unless($conversation->isVisibleTo($me), 404);
        if (! $request->filled('body') && $request->filled('draft')) {
            $request->merge(['body' => $request->input('draft')]); // "Send as written"
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'original' => ['nullable', 'string', 'max:2000'], 'original_lang' => ['nullable', 'string', 'max:8']]);
        if (! $conversation->canReplyFreely()) {
            return back()->withErrors(['body' => __('WhatsApp allows a normal reply only within 24 hours of the client\'s last message. Send an approved template from Marketing instead.')]);
        }
        $res = $client->sendText($conversation->number, (string) $conversation->wa_id, $data['body']);
        WaMessage::create(['wa_conversation_id' => $conversation->id, 'direction' => 'out', 'body' => $data['body'], 'type' => 'text', 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'user_id' => $me->id, 'language' => $conversation->language, 'sent_at' => now(),
            // What the salesperson wrote before translation, kept with the message.
            'translation' => filled($data['original'] ?? null) && $data['original'] !== $data['body'] ? $data['original'] : null,
            'translation_lang' => filled($data['original'] ?? null) ? mb_substr((string) ($data['original_lang'] ?? ''), 0, 8) : null]);
        $conversation->forceFill(['last_message_at' => now(), 'waiting_callback' => $res['ok'] ? false : $conversation->waiting_callback,
            // A person is talking now: the chatbot stays quiet in this chat for a while.
            'bot_paused_until' => $conversation->bot_paused_until?->isFuture() && $conversation->bot_paused_until->gt(now()->addHours(\App\Services\WhatsApp\ChatBot::humanPauseHours()))
                ? $conversation->bot_paused_until : now()->addHours(\App\Services\WhatsApp\ChatBot::humanPauseHours()),
            'assigned_to' => $conversation->assigned_to ?? $me->id])->save();
        if ($res['ok'] && $conversation->lead_id) {
            Lead::whereKey($conversation->lead_id)->update(['last_contact_at' => now()]);
        }

        return $res['ok'] ? back()->with('ok', __('Sent.')) : back()->withErrors(['body' => __('Not sent: :e', ['e' => $res['error']])])->withInput();
    }

    public function done(Request $request, WaConversation $conversation)
    {
        abort_unless($conversation->isVisibleTo($request->user()), 404);
        $conversation->forceFill(['waiting_callback' => false, 'urgent' => false])->save();
        AuditLog::record('inbox.done', "conversation:{$conversation->id}");

        return back()->with('ok', __('Marked as handled.'));
    }

    /** Give the chat to a team member (managers and admins). */
    public function assign(Request $request, WaConversation $conversation)
    {
        $me = $request->user();
        abort_unless($me->canManage() && $conversation->isVisibleTo($me), 404);
        $id = $request->validate(['assigned_to' => ['nullable', 'integer']])['assigned_to'] ?? null;
        $to = $id ? User::active()->visibleTo($me)->find($id) : null;
        if ($id && ! $to) {
            return back()->withErrors(['assigned_to' => __('Choose a person from your team.')]);
        }
        $conversation->forceFill(['assigned_to' => $to?->id])->save();
        if ($to && $conversation->lead_id) {
            Lead::whereKey($conversation->lead_id)->whereNull('owner_id')->update(['owner_id' => $to->id]);
        }
        if ($to && $to->id !== $me->id) {
            $to->notify(new ChatAssigned($conversation, $me->name));
        }
        AuditLog::record('inbox.assign', "conversation:{$conversation->id}", ['to' => $to?->id]);

        return back()->with('ok', $to ? __('Chat given to :name.', ['name' => $to->name]) : __('Chat is now unassigned.'));
    }

    /** A note for the team inside the chat. The client never sees it. */
    public function note(Request $request, WaConversation $conversation)
    {
        $me = $request->user();
        abort_unless($conversation->isVisibleTo($me), 404);
        $body = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        WaMessage::create(['wa_conversation_id' => $conversation->id, 'direction' => 'note', 'body' => $body, 'type' => 'note', 'status' => 'note',
            'user_id' => $me->id, 'sent_at' => now()]);

        return back()->with('ok', __('Note added. Only your team sees it.'));
    }

    public function close(Request $request, WaConversation $conversation)
    {
        $me = $request->user();
        abort_unless($conversation->isVisibleTo($me), 404);
        $closing = $conversation->status !== 'closed';
        $conversation->forceFill($closing ? ['status' => 'closed', 'closed_at' => now(), 'waiting_callback' => false, 'urgent' => false, 'unread' => 0]
            : ['status' => 'open', 'closed_at' => null])->save();
        AuditLog::record($closing ? 'inbox.close' : 'inbox.reopen', "conversation:{$conversation->id}");

        return back()->with('ok', $closing ? __('Chat closed. It opens again when the client writes.') : __('Chat opened again.'));
    }

    /** Stop or start the chatbot in this one chat. */
    public function bot(Request $request, WaConversation $conversation)
    {
        $me = $request->user();
        abort_unless($conversation->isVisibleTo($me), 404);
        $stop = $conversation->botMayReply();
        $conversation->forceFill(['bot_paused_until' => $stop ? now()->addYears(5) : null, 'bot_step' => null])->save();
        AuditLog::record('inbox.bot', "conversation:{$conversation->id}", ['stopped' => $stop]);

        return back()->with('ok', $stop ? __('Chatbot stopped in this chat.') : __('Chatbot answers this chat again.'));
    }
}
