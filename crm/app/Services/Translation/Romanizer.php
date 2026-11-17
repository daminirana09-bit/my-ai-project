<?php

namespace App\Services\Translation;

/**
 * Hindi (Devanagari) → Hinglish (Roman letters), the way people type on WhatsApp:
 * "आपका ऑर्डर कल भेज देंगे" → "aapka ordar kal bhej denge". Used when the client writes in Hinglish.
 */
class Romanizer
{
    private const CONS = [
        'क' => 'k', 'ख' => 'kh', 'ग' => 'g', 'घ' => 'gh', 'ङ' => 'n', 'च' => 'ch', 'छ' => 'chh', 'ज' => 'j', 'झ' => 'jh', 'ञ' => 'n',
        'ट' => 't', 'ठ' => 'th', 'ड' => 'd', 'ढ' => 'dh', 'ण' => 'n', 'त' => 't', 'थ' => 'th', 'द' => 'd', 'ध' => 'dh', 'न' => 'n',
        'प' => 'p', 'फ' => 'ph', 'ब' => 'b', 'भ' => 'bh', 'म' => 'm', 'य' => 'y', 'र' => 'r', 'ल' => 'l', 'व' => 'v', 'श' => 'sh',
        'ष' => 'sh', 'स' => 's', 'ह' => 'h', 'ळ' => 'l', 'क़' => 'q', 'ख़' => 'kh', 'ग़' => 'g', 'ज़' => 'z', 'ड़' => 'd', 'ढ़' => 'rh', 'फ़' => 'f', 'य़' => 'y',
    ];

    private const VOWELS = ['अ' => 'a', 'आ' => 'aa', 'इ' => 'i', 'ई' => 'ee', 'उ' => 'u', 'ऊ' => 'oo', 'ऋ' => 'ri', 'ए' => 'e', 'ऐ' => 'ai', 'ओ' => 'o', 'औ' => 'au', 'ऑ' => 'o', 'ऍ' => 'e'];

    private const MATRAS = ['ा' => 'aa', 'ि' => 'i', 'ी' => 'ee', 'ु' => 'u', 'ू' => 'oo', 'ृ' => 'ri', 'े' => 'e', 'ै' => 'ai', 'ो' => 'o', 'ौ' => 'au', 'ॉ' => 'o', 'ॅ' => 'e'];

    /** Very common words, written the way people type them. */
    private const WORDS = [
        'में' => 'mein', 'मैं' => 'main', 'नहीं' => 'nahi', 'यह' => 'yeh', 'वह' => 'woh', 'ये' => 'ye', 'वो' => 'wo', 'कि' => 'ki', 'जी' => 'ji',
        'भी' => 'bhi', 'ही' => 'hi', 'नई' => 'nayi', 'गई' => 'gayi', 'हम' => 'hum', 'तुम' => 'tum', 'और' => 'aur', 'कृपया' => 'kripya',
        'धन्यवाद' => 'dhanyavaad', 'हाँ' => 'haan', 'हां' => 'haan', 'क्या' => 'kya', 'आप' => 'aap', 'है' => 'hai', 'हैं' => 'hain',
    ];

    public function romanize(string $text): string
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        $text = strtr($text, ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9', '।' => '.', '॥' => '.']);

        return preg_replace_callback('/[\x{0900}-\x{097F}]+/u', fn ($m) => $this->word($m[0]), $text) ?? $text;
    }

    private function word(string $w): string
    {
        if (isset(self::WORDS[$w])) {
            return self::WORDS[$w];
        }
        $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
        $units = []; // [letters, vowel, kind]  kind: inherent | explicit | none | vowel
        for ($i = 0, $n = count($chars); $i < $n; $i++) {
            $c = $chars[$i];
            if (($chars[$i + 1] ?? '') === '़' && isset(self::CONS[$c.'़'])) {
                $c .= '़';
                $i++;
            }
            if (isset(self::CONS[$c])) {
                $next = $chars[$i + 1] ?? '';
                if (isset(self::MATRAS[$next])) {
                    $units[] = [self::CONS[$c], self::MATRAS[$next], 'explicit'];
                    $i++;
                } elseif ($next === '्') {
                    $units[] = [self::CONS[$c], '', 'none'];
                    $i++;
                } else {
                    $units[] = [self::CONS[$c], 'a', 'inherent'];
                }
            } elseif (isset(self::VOWELS[$c])) {
                $units[] = ['', self::VOWELS[$c], 'vowel'];
            } elseif ($c === 'ं' || $c === 'ँ') {
                $units[] = ['n', '', 'sign'];
            } elseif ($c === 'ः') {
                $units[] = ['h', '', 'sign'];
            }
        }
        $last = count($units) - 1;
        // Schwa deletion: the inherent "a" is not spoken at the end of a word…
        if ($last > 0 && $units[$last][2] === 'inherent') {
            $units[$last][1] = '';
            $units[$last][2] = 'none';
        }
        // …nor between two spoken vowels (समझते → samajhte), worked right to left.
        for ($i = $last - 1; $i >= 1; $i--) {
            $prevVoiced = $units[$i - 1][1] !== '';
            $nextVoiced = isset($units[$i + 1]) && $units[$i + 1][0] !== '' && $units[$i + 1][1] !== '';
            if ($units[$i][2] === 'inherent' && $prevVoiced && $nextVoiced) {
                $units[$i][1] = '';
                $units[$i][2] = 'none';
            }
        }
        // Word endings are typed short: aapka, aapki, karu (not aapkaa, aapkee, karoo).
        if ($last >= 0 && $units[$last][0] !== '') {
            $units[$last][1] = ['aa' => 'a', 'ee' => 'i', 'oo' => 'u'][$units[$last][1]] ?? $units[$last][1];
        }
        $out = '';
        foreach ($units as $k => [$letters, $vowel, $kind]) {
            if ($kind === 'sign' && $letters === 'n') {
                $next = $units[$k + 1][0] ?? '';
                $letters = in_array(substr($next, 0, 1), ['p', 'b', 'm'], true) ? 'm' : 'n';
            }
            $out .= $letters.$vowel;
        }

        return $out;
    }
}
