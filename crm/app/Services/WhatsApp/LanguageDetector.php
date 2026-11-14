<?php

namespace App\Services\WhatsApp;

/** Works out which language a client wrote in, from the script and a few common words. No outside service. */
class LanguageDetector
{
    public const NAMES = [
        'en' => ['English', 'English'], 'hing' => ['Hinglish', 'Hinglish'], 'hi' => ['Hindi', 'हिन्दी'], 'mr' => ['Marathi', 'मराठी'],
        'ne' => ['Nepali', 'नेपाली'], 'gu' => ['Gujarati', 'ગુજરાતી'], 'bn' => ['Bengali', 'বাংলা'], 'as' => ['Assamese', 'অসমীয়া'],
        'pa' => ['Punjabi', 'ਪੰਜਾਬੀ'], 'or' => ['Odia', 'ଓଡ଼ିଆ'], 'ta' => ['Tamil', 'தமிழ்'], 'te' => ['Telugu', 'తెలుగు'],
        'kn' => ['Kannada', 'ಕನ್ನಡ'], 'ml' => ['Malayalam', 'മലയാളം'], 'ur' => ['Urdu', 'اردو'], 'sat' => ['Santali', 'ᱥᱟᱱᱛᱟᱲᱤ'],
        'mni' => ['Manipuri', 'ꯃꯤꯇꯩꯂꯣꯟ'],
    ];

    private const HINGLISH = ['hai', 'hain', 'kya', 'nahi', 'nahin', 'kab', 'bhej', 'bhejo', 'bhejiye', 'bhai', 'aap', 'apka', 'aapka', 'karo', 'kar', 'chahiye',
        'kitna', 'kitne', 'raha', 'rahe', 'hoga', 'theek', 'thik', 'haan', 'han', 'sahab', 'maal', 'dena', 'milega', 'abhi', 'kal', 'aaj', 'mera', 'mere', 'humko', 'hume', 'hamara', 'jaldi', 'bata', 'batao', 'ji'];

    public function detect(?string $text): string
    {
        $t = trim((string) $text);
        if ($t === '') {
            return 'en';
        }
        $scripts = [
            'Devanagari' => '/\p{Devanagari}/u', 'Bengali' => '/\p{Bengali}/u', 'Gurmukhi' => '/\p{Gurmukhi}/u', 'Gujarati' => '/\p{Gujarati}/u',
            'Oriya' => '/\p{Oriya}/u', 'Tamil' => '/\p{Tamil}/u', 'Telugu' => '/\p{Telugu}/u', 'Kannada' => '/\p{Kannada}/u',
            'Malayalam' => '/\p{Malayalam}/u', 'Arabic' => '/\p{Arabic}/u', 'Ol_Chiki' => '/\p{Ol_Chiki}/u', 'Meetei_Mayek' => '/\p{Meetei_Mayek}/u',
        ];
        $best = null;
        $bestCount = 0;
        foreach ($scripts as $name => $re) {
            $n = preg_match_all($re, $t);
            if ($n > $bestCount) {
                [$best, $bestCount] = [$name, $n];
            }
        }

        return match ($best) {
            'Devanagari' => $this->devanagari($t),
            'Bengali' => preg_match('/[ৰৱ]/u', $t) ? 'as' : 'bn',
            'Gurmukhi' => 'pa', 'Gujarati' => 'gu', 'Oriya' => 'or', 'Tamil' => 'ta', 'Telugu' => 'te', 'Kannada' => 'kn',
            'Malayalam' => 'ml', 'Arabic' => 'ur', 'Ol_Chiki' => 'sat', 'Meetei_Mayek' => 'mni',
            default => $this->latin($t),
        };
    }

    private function devanagari(string $t): string
    {
        foreach (['आहे', 'आम्ही', 'आम्हाला', 'तुम्ही', 'तुमच्या', 'पाहिजे', 'काय', 'झाले', 'मिळत', 'करतो', 'नाही आहे'] as $w) {
            if (str_contains($t, $w)) {
                return 'mr';
            }
        }
        foreach (['छ ', 'छन्', 'हुन्छ', 'गर्नुहोस्', 'हजुर'] as $w) {
            if (str_contains($t.' ', $w)) {
                return 'ne';
            }
        }

        return 'hi';
    }

    private function latin(string $t): string
    {
        $words = preg_split('/[^a-z]+/', strtolower($t), -1, PREG_SPLIT_NO_EMPTY);

        return count(array_intersect($words, self::HINGLISH)) > 0 ? 'hing' : 'en';
    }
}
