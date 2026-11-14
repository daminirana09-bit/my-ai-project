<?php

namespace App\Services\WhatsApp;

/** Spots what the client is asking for, in English, Hinglish and the main Indian scripts. */
class IntentDetector
{
    /** Checked in this order; the first match wins (urgent first). */
    private const RULES = [
        'complaint' => ['urgent', 'complaint', 'complain', 'damage', 'damaged', 'wrong', 'galat', 'kharab', 'broken', 'leak', 'expired', 'short', 'missing',
            'शिकायत', 'खराब', 'गलत', 'तुरंत', 'ज़रूरी', 'तक्रार', 'ખરાબ', 'ફરિયાદ', 'অভিযোগ', 'খারাপ', 'புகார்', 'ఫిర్యాదు', 'ದೂರು', 'പരാതി', 'ਸ਼ਿਕਾਇਤ', 'شکایت'],
        'payment' => ['payment', 'paisa', 'paise', 'invoice', 'bill', 'ledger', 'outstanding', 'upi', 'neft', 'rtgs', 'cheque', 'balance',
            'भुगतान', 'पेमेंट', 'बिल', 'पैसे', 'પેમેન્ટ', 'બિલ', 'পেমেন্ট', 'বিল', 'பணம்', 'చెల్లింపు', 'ಪಾವತಿ', 'പേയ്മെന്റ്', 'ਪੇਮੈਂਟ', 'ادائیگی'],
        'order_status' => ['dispatch', 'dispatched', 'delivery', 'deliver', 'delivered', 'tracking', 'lr', 'transport', 'courier', 'shipped', 'status', 'kab aayega', 'kab tak', 'pahuncha',
            'डिलीवरी', 'डिस्पैच', 'कब आएगा', 'कब तक', 'ડિલિવરી', 'ক্যুরিয়ার', 'ডেলিভারি', 'டெலிவரி', 'డెలివరీ', 'ಡೆಲಿವರಿ', 'ഡെലിവറി', 'ਡਿਲੀਵਰੀ', 'ڈیلیوری'],
        'sample' => ['sample', 'samples', 'सैंपल', 'नमूना', 'સેમ્પલ', 'স্যাম্পল', 'மாதிரி', 'శాంపిల్', 'ಸ್ಯಾಂಪಲ್', 'സാമ്പിൾ', 'ਸੈਂਪਲ', 'سیمپل'],
        'documents' => ['coa', 'gmp', 'copp', 'licence', 'license', 'certificate', 'msds', 'stability', 'dl copy', 'सर्टिफिकेट', 'लाइसेंस', 'પ્રમાણપત્ર', 'লাইসেন্স', 'சான்றிதழ்', 'లైసెన్స్'],
        'price' => ['rate', 'rates', 'price', 'prices', 'pricelist', 'price list', 'rate list', 'kitna', 'cost', 'ptr', 'mrp', 'quotation', 'quote', 'scheme', 'discount',
            'भाव', 'रेट', 'कीमत', 'दर', 'ભાવ', 'রেট', 'দাম', 'விலை', 'ధర', 'ಬೆಲೆ', 'ದರ', 'വില', 'ਰੇਟ', 'ਕੀਮਤ', 'ریٹ', 'قیمت', 'ଦର'],
        'new_order' => ['order', 'chahiye', 'need', 'require', 'required', 'want', 'book', 'booking', 'purchase', 'buy',
            'ऑर्डर', 'चाहिए', 'हवे', 'ઓર્ડર', 'જોઈએ', 'অর্ডার', 'লাগবে', 'ஆர்டர்', 'ఆర్డర్', 'ಆರ್ಡರ್', 'ഓർഡർ', 'ਆਰਡਰ', 'آرڈر', 'ଅର୍ଡର'],
        'greeting' => ['hi', 'hello', 'hii', 'hey', 'namaste', 'namaskar', 'good morning', 'good evening', 'नमस्ते', 'नमस्कार', 'નમસ્તે', 'নমস্কার', 'வணக்கம்', 'నమస్తే', 'ನಮಸ್ಕಾರ', 'നമസ്കാരം', 'ਸਤ ਸ੍ਰੀ ਅਕਾਲ', 'السلام'],
    ];

    public function detect(?string $text): string
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return 'other';
        }
        foreach (self::RULES as $intent => $words) {
            foreach ($words as $w) {
                $isLatin = preg_match('/^[a-z ]+$/', $w);
                if ($isLatin ? preg_match('/(?<![a-z])'.preg_quote($w, '/').'(?![a-z])/u', $t) : str_contains($t, $w)) {
                    return $intent;
                }
            }
        }

        return 'other';
    }

    public function isUrgent(?string $text): bool
    {
        return $this->detect($text) === 'complaint' || str_contains(mb_strtolower((string) $text), 'urgent');
    }
}
