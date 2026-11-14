<?php

namespace App\Services\Leads;

/**
 * Turns an enquiry from any portal into LeadData. Portals name their fields differently
 * (SENDER_MOBILE, sender_mobile, mobile, phone_number…), so each value is looked up
 * under all the names in use.
 */
class Normalizer
{
    private const KEYS = [
        'externalId' => ['unique_query_id', 'rfi_id', 'inquiry_id', 'enquiry_id', 'inq_id', 'leadid', 'lead_id', 'query_id', 'id'],
        'name' => ['sender_name', 'full_name', 'fullname', 'name', 'contact_name', 'contact_person', 'buyer_name', 'customer_name', 'your_name', 'client_name', 'person_name'],
        'phone' => ['sender_mobile', 'mobile', 'mobile_number', 'mobileno', 'mobile_no', 'phone_number', 'phone', 'sender_phone', 'contact_number',
            'buyer_mobile', 'whatsapp', 'whatsapp_number', 'whatsapp_no', 'sender_mobile_alt', 'phone_no', 'contact_no', 'work_phone', 'your_phone', 'your_mobile',
            'mobile_phone', 'cell', 'contact', 'callfrom', 'call_from', 'caller', 'caller_number', 'caller_id', 'customer_number', 'from_number', 'cli', 'msisdn'],
        'email' => ['sender_email', 'email', 'email_address', 'email_id', 'emailid', 'e_mail', 'mail', 'buyer_email', 'work_email', 'sender_email_alt', 'your_email'],
        'firm' => ['sender_company', 'sender_co', 'company', 'company_name', 'firm', 'firm_name', 'organisation', 'organization', 'business_name', 'buyer_company', 'your_company', 'shop_name'],
        'city' => ['sender_city', 'city', 'buyer_city', 'your_city', 'location', 'district', 'area'],
        'state' => ['sender_state', 'state', 'region', 'buyer_state'],
        'product' => ['query_product_name', 'product_name', 'product', 'products', 'product_interested', 'products_interested', 'interested_in', 'query_mcat_name', 'subject', 'your_subject', 'category', 'service', 'enquiry_for', 'looking_for'],
        'message' => ['query_message', 'message', 'enquiry', 'inquiry', 'enquiry_message', 'inquiry_message', 'requirement', 'requirements', 'description', 'comments', 'comment',
            'query', 'details', 'your_message', 'enquiry_details', 'buyer_requirement', 'additional_details'],
        'campaign' => ['campaign_name', 'campaign', 'utm_campaign', 'form_name', 'ad_name', 'adset_name'],
    ];

    /** Any portal's record. */
    public function generic(array $row): LeadData
    {
        $flat = $this->flatten($row);
        $d = new LeadData;
        $d->fieldNames = array_keys($flat);
        foreach (self::KEYS as $prop => $names) {
            foreach ($names as $n) {
                if (isset($flat[$n]) && ($v = $this->clean($flat[$n], $prop === 'message' ? 3000 : 150)) !== null) {
                    $d->$prop = $v;
                    break;
                }
            }
        }
        if ($d->name === null && isset($flat['first_name'])) {
            $d->name = $this->clean(trim(($flat['first_name'] ?? '').' '.($flat['last_name'] ?? '')), 150);
        }
        $d->phone = $this->phone($d->phone);
        $d->email = $this->email($d->email);
        $d->externalId = $d->externalId !== null ? mb_substr($d->externalId, 0, 100) : null;
        if (isset($flat['tags'])) {
            $d->tags = array_merge($d->tags, $this->tagList($flat['tags']));
        }
        if (isset($flat['source']) && is_string($flat['source'])) {
            $d->source = $this->clean($flat['source'], 40);
        }

        return $d;
    }

    /** IndiaMART Lead Manager record (pull or push). */
    public function indiamart(array $row): LeadData
    {
        $d = $this->generic($row);
        $flat = $this->flatten($row);
        $d->tags[] = match (strtoupper((string) ($flat['query_type'] ?? ''))) {
            'B' => 'Buy-lead',
            'P' => 'Call (PNS)',
            'W' => 'Direct enquiry',
            'BIZ' => 'Catalog enquiry',
            default => null,
        };
        if ($d->phone === null && isset($flat['sender_phone'])) {
            $d->phone = $this->phone((string) $flat['sender_phone']);
        }
        $d->tags = array_values(array_filter($d->tags));

        return $d;
    }

    /** Facebook / Instagram lead (from the Graph API). */
    public function facebook(array $lead): LeadData
    {
        $fields = [];
        $questions = [];
        foreach ((array) ($lead['field_data'] ?? []) as $f) {
            $key = strtolower((string) ($f['name'] ?? ''));
            $val = implode(', ', array_map('strval', (array) ($f['values'] ?? [])));
            $fields[$key] = $val;
            if (! in_array($key, ['full_name', 'first_name', 'last_name', 'phone_number', 'email', 'city', 'state', 'company_name', 'job_title', 'zip_code', 'post_code', 'country'], true)) {
                $questions[] = str_replace('_', ' ', $key).': '.$val;
            }
        }
        $d = $this->generic($fields);
        $d->externalId = isset($lead['id']) ? (string) $lead['id'] : $d->externalId;
        $d->message = $questions ? $this->clean(implode("\n", $questions), 3000) : $d->message;
        $d->campaign = $this->clean($lead['campaign_name'] ?? $lead['ad_name'] ?? null, 150);
        $d->source = ($lead['platform'] ?? '') === 'ig' ? 'Instagram Ads' : 'Facebook Ads';
        $d->tags = array_values(array_filter([$d->source, $d->campaign, ($lead['is_organic'] ?? false) ? 'Organic' : null, $this->clean($lead['form_name'] ?? null, 60)]));
        $d->fieldNames = array_keys($fields);

        return $d;
    }

    /** Google Ads lead form webhook body. */
    public function google(array $body): LeadData
    {
        $fields = [];
        $other = [];
        foreach ((array) ($body['user_column_data'] ?? []) as $c) {
            $id = strtoupper((string) ($c['column_id'] ?? ''));
            $val = (string) ($c['string_value'] ?? '');
            $map = ['FULL_NAME' => 'full_name', 'FIRST_NAME' => 'first_name', 'LAST_NAME' => 'last_name', 'PHONE_NUMBER' => 'phone_number',
                'WORK_PHONE' => 'work_phone', 'EMAIL' => 'email', 'WORK_EMAIL' => 'work_email', 'CITY' => 'city', 'REGION' => 'state', 'COMPANY_NAME' => 'company_name'];
            if (isset($map[$id])) {
                $fields[$map[$id]] = $val;
            } elseif ($val !== '') {
                $other[] = ($c['column_name'] ?? $id).': '.$val;
            }
        }
        $d = $this->generic($fields);
        $d->externalId = isset($body['lead_id']) ? (string) $body['lead_id'] : null;
        $d->message = $other ? $this->clean(implode("\n", $other), 3000) : null;
        $d->campaign = isset($body['campaign_id']) ? 'Campaign '.preg_replace('/\D/', '', (string) $body['campaign_id']) : null;
        $d->test = (bool) ($body['is_test'] ?? false);
        $d->tags = array_values(array_filter(['Google Ads', $d->campaign]));
        $d->fieldNames = array_keys($fields);

        return $d;
    }

    /** The list of enquiries inside a portal's reply: a plain list, or under RESPONSE / data / leads… */
    public function records(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        if (array_is_list($json)) {
            return array_values(array_filter($json, 'is_array'));
        }
        foreach ($json as $k => $v) {
            if (in_array(strtolower((string) $k), ['response', 'data', 'leads', 'inquiries', 'enquiries', 'inquiry', 'enquiry', 'result', 'results', 'records', 'lead'], true) && is_array($v)) {
                return array_is_list($v) ? array_values(array_filter($v, 'is_array')) : [$v];
            }
        }

        return [];
    }

    public function tagList(mixed $tags): array
    {
        $list = is_array($tags) ? $tags : explode(',', (string) $tags);

        return collect($list)->map(fn ($t) => is_scalar($t) ? $this->clean((string) $t, 40) : null)->filter()->unique()->take(10)->values()->all();
    }

    private function flatten(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (is_array($v) && ! array_is_list($v)) {
                foreach ($v as $k2 => $v2) {
                    if (! is_array($v2)) {
                        $out[self::key((string) $k2)] ??= $v2;
                    }
                }
            } else {
                $out[self::key((string) $k)] = $v;
            }
        }

        return $out;
    }

    /** "Mobile No." → mobile_no, "your-email" → your_email, "SENDER_MOBILE" → sender_mobile */
    public static function key(string $k): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($k))) ?? '', '_');
    }

    private function clean(mixed $v, int $max): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $s = strip_tags((string) $v);
        $s = preg_replace('/[^\P{C}\n]+/u', '', $s) ?? '';       // control characters out, new lines kept
        $s = trim(preg_replace("/[ \t]+/", ' ', $s) ?? '');

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private function phone(?string $p): ?string
    {
        if ($p === null) {
            return null;
        }
        $clean = preg_replace('/[^0-9+]/', '', $p) ?? '';
        $digits = preg_replace('/\D/', '', $clean) ?? '';

        return strlen($digits) >= 7 && strlen($digits) <= 15 ? mb_substr($clean, 0, 20) : null;
    }

    private function email(?string $e): ?string
    {
        return $e !== null && filter_var($e, FILTER_VALIDATE_EMAIL) ? mb_substr(strtolower($e), 0, 150) : null;
    }
}
