<?php

namespace App\Services\Leads;

/**
 * Where a website enquiry really came from, from the hidden fields the form snippet fills:
 * Google Ads (gclid / paid), Facebook or Instagram ads (fbclid / paid), Google search, or the website.
 */
class WebsiteSource
{
    /** @return array{0: string, 1: string[], 2: ?string} source, tags, campaign */
    public static function classify(array $in): array
    {
        $s = strtolower(trim((string) ($in['utm_source'] ?? '')));
        $m = strtolower(trim((string) ($in['utm_medium'] ?? '')));
        $ref = strtolower((string) ($in['referrer'] ?? ''));
        $refHost = (string) (parse_url($ref, PHP_URL_HOST) ?: '');
        $paid = in_array($m, ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'ads', 'ad', 'display', 'cpm'], true);
        $campaign = isset($in['utm_campaign']) && is_scalar($in['utm_campaign']) ? mb_substr(strip_tags((string) $in['utm_campaign']), 0, 150) ?: null : null;

        $source = match (true) {
            filled($in['gclid'] ?? null) || filled($in['gbraid'] ?? null) || filled($in['wbraid'] ?? null) || (str_contains($s, 'google') && $paid) => 'Google Ads',
            in_array($s, ['ig', 'instagram'], true) && ($paid || filled($in['fbclid'] ?? null)) => 'Instagram Ads',
            filled($in['fbclid'] ?? null) || (in_array($s, ['fb', 'facebook', 'meta'], true) && $paid) => 'Facebook Ads',
            str_contains($s, 'google') || preg_match('/(^|\.)google\./', $refHost) === 1 => 'Google (search)',
            str_contains($s, 'gmb') || str_contains($s, 'business') || str_contains($refHost, 'maps.google') => 'Google Maps / Business',
            default => 'Website',
        };
        $tags = array_values(array_unique(array_filter([$source, 'Website', $campaign])));

        return [$source, $tags, $campaign];
    }
}
