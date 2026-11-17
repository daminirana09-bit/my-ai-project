<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Google Cloud Translation (the company's own key and billing). */
class GoogleProvider implements Provider
{
    public const URL = 'https://translation.googleapis.com/language/translate/v2';

    public const CODES = [
        'en' => 'en', 'hi' => 'hi', 'mr' => 'mr', 'gu' => 'gu', 'bn' => 'bn', 'pa' => 'pa', 'ta' => 'ta', 'te' => 'te', 'kn' => 'kn',
        'ml' => 'ml', 'or' => 'or', 'as' => 'as', 'ur' => 'ur', 'ne' => 'ne', 'kok' => 'gom', 'mai' => 'mai', 'sd' => 'sd', 'sa' => 'sa',
        'doi' => 'doi', 'mni' => 'mni-Mtei',
    ];

    public function __construct(private string $key) {}

    public function supports(string $lang): bool
    {
        return isset(self::CODES[$lang]);
    }

    public function translate(array $texts, ?string $from, string $to): array
    {
        if ($from !== null && str_ends_with($from, '-Latn')) {
            $from = null; // typed in English letters: the service works out the language
        }
        if (! $this->supports($to)) {
            throw new TranslationFailed('Google does not translate into this language. Try Bhashini.');
        }
        $body = ['q' => array_values($texts), 'target' => self::CODES[$to], 'format' => 'text'];
        if ($from !== null && $this->supports($from)) {
            $body['source'] = self::CODES[$from];
        }
        try {
            $res = Http::acceptJson()->timeout(20)->post(self::URL.'?key='.urlencode($this->key), $body);
        } catch (Throwable $e) {
            throw new TranslationFailed('Could not reach Google.');
        }
        $out = $res->json('data.translations');
        if (! $res->successful() || ! is_array($out) || count($out) !== count($texts)) {
            throw new TranslationFailed('Google: '.mb_substr((string) ($res->json('error.message') ?? 'HTTP '.$res->status()), 0, 200));
        }

        return array_map(fn ($o) => html_entity_decode((string) ($o['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $out);
    }
}
