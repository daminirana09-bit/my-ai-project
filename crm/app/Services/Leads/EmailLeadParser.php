<?php

namespace App\Services\Leads;

/**
 * Turns an enquiry email into LeadData: a portal's notification (IndiaMART, Sulekha, a pharma
 * franchise portal…), the same mail forwarded from Gmail, or a client writing directly.
 * Labelled lines ("Mobile: 98…", tables in HTML) are read first; a phone number or email
 * anywhere in the text is the fallback.
 */
class EmailLeadParser
{
    /** Sender contains → source shown on the lead. Admins can add their own rules. */
    public const DEFAULT_RULES = [
        'indiamart' => 'IndiaMART', 'tradeindia' => 'TradeIndia', 'exportersindia' => 'ExportersIndia', 'justdial' => 'JustDial',
        'sulekha' => 'Sulekha', 'alibaba' => 'Alibaba', 'go4worldbusiness' => 'Go4WorldBusiness', 'tradekey' => 'TradeKey',
        'pharmahopers' => 'Pharmahopers', 'facebookmail' => 'Facebook', 'linkedin' => 'LinkedIn', 'google' => 'Google',
    ];

    public function __construct(private Normalizer $normalizer) {}

    /**
     * @param  string  $mailbox  the address being read (leads@company.com), to ignore our own mail
     * @param  string  $rules    extra admin rules, "sender part = Source, …"
     * @return array{0: ?LeadData, 1: string} lead or null, and why it was skipped
     */
    public function parse(MimeMessage $m, string $mailbox, string $rules = ''): array
    {
        $mailbox = strtolower(trim($mailbox));
        $ownDomain = substr(strrchr($mailbox, '@') ?: '', 1);
        [$fromName, $fromAddr] = MimeMessage::address($m->header('from'));
        [$replyName, $replyAddr] = MimeMessage::address($m->header('reply-to'));
        $text = $this->text($m);

        // Forwarded by someone at the company (Gmail "Forward" or a filter): the real sender is inside the mail.
        $forwarded = $ownDomain !== '' && str_ends_with($fromAddr, '@'.$ownDomain);
        if ($forwarded && preg_match('/^\s*(?:From|Fwd?\s*From)\s*:\s*(.+)$/mi', $text, $f)) {
            [$fromName, $fromAddr] = MimeMessage::address($f[1]);
        }
        if ($fromAddr === $mailbox) {
            return [null, 'own mail'];
        }

        $source = $this->sourceFor($fromAddr.' '.$fromName, $rules);
        $portal = $source !== null;
        $automatic = $this->isAutomatic($m, $fromAddr);
        if ($automatic && ! $portal) {
            return [null, 'newsletter or automatic mail'];
        }

        $fields = $this->labelled($text);
        $d = $this->normalizer->generic($fields);
        $d->fieldNames = array_keys($fields);
        $subject = trim($m->header('subject'));
        $subject = trim(preg_replace('/^((re|fwd?|fw)\s*:\s*)+/i', '', $subject) ?? '');

        if ($d->phone === null && preg_match('/(?<![\d+])(?:\+?91[\s\-]?|0)?([6-9]\d{4}[\s\-]?\d{5})(?!\d)/', $text, $p)) {
            $d->phone = preg_replace('/\D/', '', $p[1]);
        }
        $person = ! $portal && ! $automatic; // a real person wrote it (directly, or forwarded by the company)
        if ($d->email === null) {
            // A client writing to us: their address is the contact. From a portal: the buyer's address in the text, if any.
            $candidate = $replyAddr !== '' && ! str_ends_with($replyAddr, '@'.$ownDomain) && $this->sourceFor($replyAddr, $rules) === null ? $replyAddr : null;
            $candidate ??= $person ? $fromAddr : null;
            if ($candidate === null && preg_match_all('/[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+/', $text, $all)) {
                foreach ($all[0] as $e) {
                    $e = strtolower($e);
                    if ($e !== $mailbox && ! str_ends_with($e, '@'.$ownDomain) && $this->sourceFor($e, $rules) === null && ! $this->isNoReply($e)) {
                        $candidate = $e;
                        break;
                    }
                }
            }
            $d->email = $candidate && filter_var($candidate, FILTER_VALIDATE_EMAIL) ? mb_substr($candidate, 0, 150) : null;
        }
        if ($d->name === null && $person && $fromName !== '') {
            $d->name = mb_substr($fromName, 0, 150);
        }
        $d->product ??= $subject !== '' ? mb_substr($subject, 0, 150) : null;
        $d->message ??= mb_substr($this->cleanBody($forwarded ? $this->afterForwardHeader($text) : $text), 0, 1500) ?: null;
        $d->externalId = ($id = trim($m->header('message-id'), " <>")) !== '' ? 'm:'.substr(hash('sha256', $id), 0, 40) : null;
        $d->source = $source ?? 'Email';
        $d->tags = array_values(array_unique(array_filter([$d->source, 'Email'])));

        if (! $d->hasContact()) {
            return [null, 'no phone number or email found'];
        }
        if ($this->isNoReply((string) $d->email) && $d->phone === null) {
            return [null, 'no phone number or email found'];
        }

        return [$d, ''];
    }

    public function sourceFor(string $sender, string $rules = ''): ?string
    {
        $sender = strtolower($sender);
        $all = self::DEFAULT_RULES;
        foreach (preg_split('/[,\n]+/', $rules) as $r) {
            if (str_contains($r, '=')) {
                [$k, $v] = array_map('trim', explode('=', $r, 2));
                if ($k !== '' && $v !== '') {
                    $all = [strtolower($k) => mb_substr(strip_tags($v), 0, 40)] + $all; // admin rules first
                }
            }
        }
        foreach ($all as $needle => $name) {
            if ($needle !== '' && str_contains($sender, $needle)) {
                return $name;
            }
        }

        return null;
    }

    /** Plain text of the mail; HTML tables become "Label: value" lines. */
    public function text(MimeMessage $m): string
    {
        $t = trim($m->text);
        if ($t !== '' && preg_match('/[:@\d]/', $t)) {
            return str_replace("\r", '', $t);
        }
        $h = $m->html;
        $h = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $h) ?? '';
        $h = preg_replace('#</t[dh]>\s*<t[dh][^>]*>#i', ': ', $h) ?? '';
        $h = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6]|table)>#i', "\n", $h) ?? '';
        $h = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $h = preg_replace("/[ \t\x{00A0}]+/u", ' ', $h) ?? '';
        $h = preg_replace("/\n\s*\n+/", "\n", $h) ?? '';

        return trim($h) !== '' ? trim($h) : $t;
    }

    /** "Label: value" lines, label made into a key (Mobile No. → mobile_no). */
    private function labelled(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n/', $text) as $line) {
            if (preg_match('/^\s*[*\-•]?\s*([\p{L}][\p{L}\p{N} .\/()_\-]{0,40}?)\s*[:：]\s*(.+?)\s*$/u', $line, $x)) {
                $key = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($x[1])) ?? '', '_');
                if ($key !== '' && ! isset($out[$key]) && ! in_array($key, ['from', 'to', 'sent', 'date', 'subject', 'cc'], true)) {
                    $out[$key] = $x[2];
                }
            }
        }

        return $out;
    }

    private function isAutomatic(MimeMessage $m, string $from): bool
    {
        $auto = strtolower($m->header('auto-submitted'));
        $prec = strtolower($m->header('precedence'));

        return ($auto !== '' && $auto !== 'no') || in_array($prec, ['bulk', 'list', 'junk'], true)
            || $m->header('list-unsubscribe') !== '' || $this->isNoReply($from);
    }

    private function isNoReply(string $addr): bool
    {
        return preg_match('/^(no-?reply|do-?not-?reply|mailer-daemon|postmaster|notifications?|alerts?)@/i', $addr) === 1;
    }

    /** The forwarded mail itself, without the "From / Date / Subject / To" block. */
    private function afterForwardHeader(string $text): string
    {
        $parts = preg_split('/-{2,} ?Forwarded message ?-*|Begin forwarded message:/i', $text, 2);
        $inner = $parts[1] ?? $text;

        return preg_replace('/^(\s*(From|Date|Sent|Subject|To|Cc)\s*:.*\n)+/i', '', ltrim($inner, "-\n ")) ?? $inner;
    }

    /** The client's own words: quoted replies and signatures are cut off. */
    private function cleanBody(string $text): string
    {
        $text = preg_split('/\n\s*(On .{5,80} wrote:|-{2,} ?Forwarded message|-- ?\n|>)/', "\n".$text)[0] ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }
}
