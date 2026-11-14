<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Services\WhatsApp\Client;
use App\Services\WhatsApp\LanguageDetector;
use App\Services\WhatsApp\ReplySuggester;
use Illuminate\Http\Request;

/** WhatsApp chats of the numbers this person may see, with ready replies in the client's language. */
class InboxController extends Controller
{
    public function index(Request $request, ?WaConversation $conversation = null)
    {
        $me = $request->user();
        $filter = $request->query('show');
        $list = WaConversation::visibleTo($me)->with(['lead:id,firm_name,owner_id', 'number:id,label,user_id'])
            ->when($filter === 'unread', fn ($q) => $q->where('unread', '>', 0))
            ->when($filter === 'callback', fn ($q) => $q->where('waiting_callback', true))
            ->when($filter === 'urgent', fn ($q) => $q->where('urgent', true))
            ->orderByDesc('last_message_at')->limit(100)->get();

        $current = null;
        $suggestions = collect();
        if ($conversation) {
            abort_unless($conversation->isVisibleTo($me), 404);
            $conversation->load(['messages.user:id,name', 'lead.owner:id,name', 'number.user']);
            if ($conversation->unread) {
                $conversation->forceFill(['unread' => 0])->save();
            }
            $current = $conversation;
            $suggestions = app(ReplySuggester::class)->suggest($conversation, app()->getLocale());
        }

        return view('inbox', ['list' => $list, 'current' => $current, 'suggestions' => $suggestions, 'filter' => $filter, 'languages' => LanguageDetector::NAMES]);
    }

    public function send(Request $request, WaConversation $conversation, Client $client)
    {
        $me = $request->user();
        abort_unless($conversation->isVisibleTo($me), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        if (! $conversation->canReplyFreely()) {
            return back()->withErrors(['body' => __('WhatsApp allows a normal reply only within 24 hours of the client\'s last message. Send an approved template from Marketing instead.')]);
        }
        $res = $client->sendText($conversation->number, (string) $conversation->wa_id, $data['body']);
        WaMessage::create(['wa_conversation_id' => $conversation->id, 'direction' => 'out', 'body' => $data['body'], 'type' => 'text', 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'user_id' => $me->id, 'language' => $conversation->language, 'sent_at' => now()]);
        $conversation->forceFill(['last_message_at' => now(), 'waiting_callback' => $res['ok'] ? false : $conversation->waiting_callback])->save();
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
}
