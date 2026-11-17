<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Microsoft Azure Translator (the company's own key; a free tier exists). */
class MicrosoftProvider implements Provider
{
    public const URL = 'https://api.cognitive.microsofttranslator.com/translate';

    public const CODES = [
        'en' => 'en', 'hi' => 'hi', 'mr' => 'mr', 'gu' => 'gu', 'bn' => 'bn', 'pa' => 'pa', 'ta' => 'ta', 'te' => 'te', 'kn' => 'kn',
        'ml' => 'ml', 'or' => 'or', 'as' => 'as', 'ur' => 'ur', 'ne' => 'ne', 'kok' => 'gom', 'mai' => 'mai', 'sd' => 'sd', 'ks' => 'ks',
        'doi' => 'doi', 'brx' => 'brx', 'mni' => 'mni',
    ];

    public function __construct(private string $key, private string $region = '') {}

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
            throw new TranslationFailed('Microsoft does not translate into this language. Try Bhashini.');
        }
        $query = ['api-version' => '3.0', 'to' => self::CODES[$to]];
        if ($from !== null && $this->supports($from)) {
            $query['from'] = self::CODES[$from];
        }
        $headers = ['Ocp-Apim-Subscription-Key' => $this->key];
        if ($this->region !== '') {
            $headers['Ocp-Apim-Subscription-Region'] = $this->region;
        }
        try {
            $res = Http::withHeaders($headers)->acceptJson()->timeout(20)->post(self::URL.'?'.http_build_query($query), array_map(fn ($t) => ['Text' => $t], array_values($texts)));
        } catch (Throwable $e) {
            throw new TranslationFailed('Could not reach Microsoft.');
        }
        $out = $res->json();
        if (! $res->successful() || ! is_array($out) || count($out) !== count($texts)) {
            throw new TranslationFailed('Microsoft: '.mb_substr((string) ($res->json('error.message') ?? 'HTTP '.$res->status()), 0, 200));
        }

        return array_map(fn ($o) => (string) ($o['translations'][0]['text'] ?? ''), $out);
    }
}
