<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappNumber;

/** Used by "Try the chatbot": records what would be sent, sends nothing. */
class SimulatedClient extends Client
{
    public array $sent = [];

    public function configured(): bool
    {
        return true;
    }

    public function sendText(WhatsappNumber $from, string $to, string $body): array
    {
        $this->sent[] = ['text' => $body, 'buttons' => []];

        return ['ok' => true, 'id' => null, 'error' => null];
    }

    public function sendButtons(WhatsappNumber $from, string $to, string $body, array $buttons): array
    {
        $this->sent[] = ['text' => $body, 'buttons' => array_values($buttons)];

        return ['ok' => true, 'id' => null, 'error' => null];
    }

    public function sendFile(WhatsappNumber $from, string $to, string $path, string $filename, string $mime, string $kind, string $caption = ''): array
    {
        $this->sent[] = ['text' => $caption, 'buttons' => [], 'file' => $filename];

        return ['ok' => true, 'id' => null, 'error' => null];
    }
}
