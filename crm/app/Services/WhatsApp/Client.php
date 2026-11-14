<?php

namespace App\Services\WhatsApp;

use App\Models\CompanySetting;
use App\Models\WhatsappNumber;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta WhatsApp Cloud API. The only outside service the CRM talks to (Rule 1): it sends exactly
 * the message meant for the client, nothing else.
 */
class Client
{
    public function configured(): bool
    {
        return filled(CompanySetting::current()?->wa('access_token'));
    }

    /** Indian 10-digit numbers get the 91 country code; everything else keeps its digits. */
    public static function toWaId(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return strlen($d) === 10 ? '91'.$d : $d;
    }

    /** @return array{ok:bool,id:?string,error:?string} */
    public function sendText(WhatsappNumber $from, string $to, string $body): array
    {
        return $this->post($from, ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]]);
    }

    /** A message approved as a template in WhatsApp Manager; needed outside the 24-hour window. */
    public function sendTemplate(WhatsappNumber $from, string $to, string $name, string $language, array $params = []): array
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];
        if ($params) {
            $template['components'] = [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], array_values($params))]];
        }

        return $this->post($from, ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => $template]);
    }

    public function sendDocument(WhatsappNumber $from, string $to, string $path, string $filename, string $caption = ''): array
    {
        return $this->sendFile($from, $to, $path, $filename, 'application/pdf', 'document', $caption);
    }

    /** Uploads the file to WhatsApp, then sends it as a document or an image. */
    public function sendFile(WhatsappNumber $from, string $to, string $path, string $filename, string $mime, string $kind, string $caption = ''): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'id' => null, 'error' => 'WhatsApp is not set up yet (WhatsApp settings).'];
        }
        $c = CompanySetting::current();
        try {
            $up = Http::withToken((string) $c->wa('access_token'))->timeout(60)
                ->attach('file', fopen($path, 'r'), $filename, ['Content-Type' => $mime])
                ->post($this->url($from->phone_number_id.'/media'), ['messaging_product' => 'whatsapp', 'type' => $mime]);
            if (! $up->successful() || ! $up->json('id')) {
                return ['ok' => false, 'id' => null, 'error' => $this->error($up->json(), $up->status())];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => 'Could not reach WhatsApp: '.class_basename($e)];
        }
        $media = ['id' => $up->json('id')] + ($caption !== '' ? ['caption' => mb_substr($caption, 0, 1000)] : []);
        if ($kind === 'document') {
            $media['filename'] = $filename;
        }

        return $this->post($from, ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => $kind, $kind => $media]);
    }

    private function post(WhatsappNumber $from, array $payload): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'id' => null, 'error' => 'WhatsApp is not set up yet (WhatsApp settings).'];
        }
        try {
            $r = Http::withToken((string) CompanySetting::current()->wa('access_token'))->acceptJson()->timeout(20)
                ->post($this->url($from->phone_number_id.'/messages'), $payload);
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => 'Could not reach WhatsApp: '.class_basename($e)];
        }
        if ($r->successful() && $r->json('messages.0.id')) {
            return ['ok' => true, 'id' => $r->json('messages.0.id'), 'error' => null];
        }

        return ['ok' => false, 'id' => null, 'error' => $this->error($r->json(), $r->status())];
    }

    private function url(string $path): string
    {
        $v = CompanySetting::current()?->wa('api_version') ?: 'v23.0';

        return 'https://graph.facebook.com/'.$v.'/'.$path;
    }

    private function error($json, int $status): string
    {
        $e = is_array($json) ? ($json['error'] ?? []) : [];

        return mb_substr(trim(($e['message'] ?? 'WhatsApp error').(isset($e['code']) ? ' (code '.$e['code'].')' : '').' [HTTP '.$status.']'), 0, 250);
    }
}
