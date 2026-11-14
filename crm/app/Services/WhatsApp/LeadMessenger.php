<?php

namespace App\Services\WhatsApp;

use App\Models\Lead;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;

/**
 * Sends something from the CRM to one client on WhatsApp: the salesperson's own number (or the
 * company number), only inside WhatsApp's 24-hour window, and kept in the chat history.
 */
class LeadMessenger
{
    public function __construct(private Client $client) {}

    /** @return array{0: ?WhatsappNumber, 1: ?WaConversation, 2: ?string, 3: ?string} number, chat, client wa_id, problem */
    public function route(Lead $lead): array
    {
        $to = Client::toWaId($lead->phone);
        if (! $to) {
            return [null, null, null, __('This client has no phone number.')];
        }
        if ($lead->whatsapp_opt_out) {
            return [null, null, $to, __('This client has asked not to get WhatsApp messages.')];
        }
        $number = WhatsappNumber::where('active', true)->where('user_id', $lead->owner_id)->first()
            ?? WhatsappNumber::where('active', true)->whereNull('user_id')->first();
        if (! $number || ! $this->client->configured()) {
            return [null, null, $to, __('No WhatsApp number is set up for this salesperson or the company.')];
        }
        $conv = WaConversation::where('whatsapp_number_id', $number->id)->where('wa_id_hash', Lead::hashPhone($to))->first();
        if (! $conv?->canReplyFreely()) {
            return [$number, $conv, $to, __('WhatsApp allows this only within 24 hours of the client\'s last message. Use "Open in my WhatsApp" instead, or ask the client to message you first.')];
        }

        return [$number, $conv, $to, null];
    }

    /** @return string|null the problem, or null when sent */
    public function text(Lead $lead, string $body, User $by): ?string
    {
        [$number, $conv, $to, $problem] = $this->route($lead);
        if ($problem) {
            return $problem;
        }
        $res = $this->client->sendText($number, $to, $body);

        return $this->record($conv, $body, 'text', $res, $by);
    }

    public function file(Lead $lead, string $path, string $filename, string $mime, string $kind, string $caption, User $by): ?string
    {
        [$number, $conv, $to, $problem] = $this->route($lead);
        if ($problem) {
            return $problem;
        }
        $res = $this->client->sendFile($number, $to, $path, $filename, $mime, $kind, $caption);

        return $this->record($conv, ($kind === 'image' ? 'Photo: ' : 'File: ').$filename, 'document', $res, $by);
    }

    private function record(WaConversation $conv, string $body, string $type, array $res, User $by): ?string
    {
        WaMessage::create(['wa_conversation_id' => $conv->id, 'direction' => 'out', 'body' => $body, 'type' => $type, 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'user_id' => $by->id, 'sent_at' => now()]);
        if ($res['ok']) {
            $conv->forceFill(['last_message_at' => now()])->save();
        }

        return $res['ok'] ? null : __('Not sent: :e', ['e' => $res['error']]);
    }

    /** A wa.me link that opens the salesperson's own WhatsApp with the message ready (any time, no 24-hour rule). */
    public static function openLink(Lead $lead, string $body): ?string
    {
        $to = Client::toWaId($lead->phone);

        return $to ? 'https://wa.me/'.$to.'?text='.rawurlencode($body) : null;
    }
}
