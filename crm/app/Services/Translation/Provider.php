<?php

namespace App\Services\Translation;

interface Provider
{
    /**
     * @param  string[]  $texts  lines to translate (never phone numbers, emails or links)
     * @param  string|null  $from  CRM language code, or null to let the service detect it
     * @return string[] translations in the same order
     *
     * @throws TranslationFailed
     */
    public function translate(array $texts, ?string $from, string $to): array;

    public function supports(string $lang): bool;
}
