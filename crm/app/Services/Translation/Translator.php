<?php

namespace App\Services\Translation;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Chat translation between the client's language and the salesperson's.
 * - Phone numbers, emails and links never go to the translation service (Rule 1): the text
 *   around them is translated and they are put back exactly.
 * - Each line is translated on its own, so line breaks stay.
 * - Numbers are compared after translation; a changed number is flagged, never sent silently.
 * - Every translated line is kept (encrypted), so the same line is never sent twice.
 */
class Translator
{
    public const PROVIDERS = ['off' => 'Off', 'bhashini' => 'Bhashini (Government of India, free)', 'google' => 'Google Cloud Translation', 'microsoft' => 'Microsoft Translator'];

    /** Languages a salesperson can read client messages in. */
    public const READ_LANGS = ['en' => 'English', 'hi' => 'हिन्दी', 'hing' => 'Hinglish'];

    private const PROTECT = '/(https?:\/\/\S+|www\.\S+|[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+|\+?\d[\d\s\-()]{6,}\d)/u';

    private ?Provider $provider = null;

    private ?CompanySetting $company;

    private Romanizer $romanizer;

    public function __construct()
    {
        $this->company = CompanySetting::current();
        $this->romanizer = new Romanizer;
    }

    public function name(): string
    {
        $p = (string) $this->company?->tr('provider', 'off');

        return isset(self::PROVIDERS[$p]) ? $p : 'off';
    }

    public function enabled(): bool
    {
        return $this->providerOrNull() !== null;
    }

    public function auto(): bool
    {
        return $this->enabled() && (bool) $this->company?->tr('auto', true);
    }

    /** The language client messages are shown in for this person. */
    public static function readerLang(User $u): string
    {
        return isset(self::READ_LANGS[$u->read_lang]) ? $u->read_lang : ($u->locale === 'hi' ? 'hi' : ($u->locale === 'hing' ? 'hing' : 'en'));
    }

    /** True when a reader of $reader understands $lang without help. */
    public static function understands(string $reader, ?string $lang): bool
    {
        $lang = $lang ?: 'en';
        $family = fn ($l) => in_array($l, ['hi', 'hing'], true) ? 'hi' : $l;

        return $family($reader) === $family($lang) || ($lang === 'hing' && $reader === 'en') || ($lang === 'en' && $reader === 'hing');
    }

    /**
     * @return array{text: string, warnings: string[]}
     *
     * @throws TranslationFailed
     */
    public function translate(string $text, ?string $from, string $to): array
    {
        $text = str_replace("\r", '', trim($text));
        $from = $from ?: null;
        if ($text === '' || $from === $to) {
            return ['text' => $text, 'warnings' => []];
        }
        // Hindi and Hinglish are the same language in two scripts.
        if (in_array($from, ['hi', 'hing'], true) && in_array($to, ['hi', 'hing'], true)) {
            return ['text' => $to === 'hing' ? $this->romanizer->romanize($text) : $text, 'warnings' => []];
        }
        $target = $to === 'hing' ? 'hi' : $to;
        $source = $from === 'hing' ? 'hi-Latn' : $from;
        if ($source !== null && $source !== 'en' && ! str_ends_with($source, '-Latn') && ! preg_match('/[^\p{Latin}\P{L}]/u', $text)) {
            $source .= '-Latn'; // an Indian language typed in English letters (e.g. Tamil in Roman script)
        }
        if ($source === $target) {
            return ['text' => $to === 'hing' ? $this->romanizer->romanize($text) : $text, 'warnings' => []];
        }
        $provider = $this->providerOrNull() ?? throw new TranslationFailed('Translation is switched off. The admin turns it on under WhatsApp settings.');

        // Split into pieces: lines, and inside lines the protected parts.
        $pieces = [];
        foreach (explode("\n", $text) as $li => $line) {
            if ($li > 0) {
                $pieces[] = ['keep', "\n"];
            }
            foreach (preg_split(self::PROTECT, $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $k => $part) {
                $pieces[] = [$k % 2 === 1 ? 'keep' : 'text', $part];
            }
        }
        $todo = [];
        foreach ($pieces as [$kind, $part]) {
            if ($kind === 'text' && preg_match('/\pL/u', $part)) {
                $todo[trim($part)] = true;
            }
        }
        $done = $this->run($provider, array_keys($todo), $source, $target);

        $out = '';
        foreach ($pieces as [$kind, $part]) {
            if ($kind === 'keep' || ! preg_match('/\pL/u', $part)) {
                $out .= $part;
            } else {
                preg_match('/^(\s*).*?(\s*)$/su', $part, $sp);
                $out .= ($sp[1] ?? '').$done[trim($part)].($sp[2] ?? '');
            }
        }
        if ($to === 'hing') {
            $out = $this->romanizer->romanize($out);
        }

        return ['text' => $out, 'warnings' => $this->numbersChanged($text, $out) ? [__('A number may have changed in translation. Check quantities, rates and dates before sending.')] : []];
    }

    /** Translations for many lines, from the saved ones first; only new lines go to the service. */
    private function run(Provider $provider, array $lines, ?string $from, string $to): array
    {
        $result = [];
        $missing = [];
        foreach ($lines as $line) {
            $key = hash_hmac('sha256', $this->name().'|'.($from ?? 'auto').'|'.$to.'|'.$line, (string) config('app.key'));
            $hit = DB::table('translation_cache')->where('key', $key)->value('text');
            if ($hit !== null) {
                try {
                    $result[$line] = decrypt($hit);

                    continue;
                } catch (\Throwable) {
                    // key changed: translate again
                }
            }
            $missing[$line] = $key;
        }
        foreach (array_chunk(array_keys($missing), 25) as $chunk) {
            $translated = $provider->translate($chunk, $from, $to);
            foreach ($chunk as $i => $line) {
                $t = trim((string) ($translated[$i] ?? ''));
                if ($t === '') {
                    throw new TranslationFailed(__('The translation came back empty. Try again or write it differently.'));
                }
                $result[$line] = $t;
                DB::table('translation_cache')->updateOrInsert(['key' => $missing[$line]], ['text' => encrypt($t), 'created_at' => now()]);
            }
        }

        return $result;
    }

    /** Compares the numbers (Indian scripts' digits too) before and after. */
    private function numbersChanged(string $a, string $b): bool
    {
        $norm = function (string $s): array {
            $s = preg_replace_callback('/\p{Nd}/u', fn ($m) => (string) \IntlChar::digit(mb_ord($m[0])), $s) ?? $s;
            preg_match_all('/\d+(?:[.,]\d+)*/', $s, $m);

            return array_map(fn ($n) => str_replace(',', '', $n), $m[0]);
        };
        $x = $norm($a);
        $y = $norm($b);
        sort($x);
        sort($y);

        return $x !== $y;
    }

    private function providerOrNull(): ?Provider
    {
        if ($this->provider) {
            return $this->provider;
        }
        $c = $this->company;

        return $this->provider = match ($this->name()) {
            'bhashini' => $c->tr('bhashini_user') && $c->tr('bhashini_key') ? new BhashiniProvider((string) $c->tr('bhashini_user'), (string) $c->tr('bhashini_key'), (string) $c->tr('bhashini_pipeline', BhashiniProvider::DEFAULT_PIPELINE)) : null,
            'google' => $c->tr('google_key') ? new GoogleProvider((string) $c->tr('google_key')) : null,
            'microsoft' => $c->tr('ms_key') ? new MicrosoftProvider((string) $c->tr('ms_key'), (string) $c->tr('ms_region', '')) : null,
            default => null,
        };
    }

    /** For tests: use a ready provider. */
    public function using(Provider $p): self
    {
        $this->provider = $p;

        return $this;
    }
}
