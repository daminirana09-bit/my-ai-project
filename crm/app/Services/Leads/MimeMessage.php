<?php

namespace App\Services\Leads;

/**
 * Reads a raw email (as stored on the mail server) into headers, plain text and HTML.
 * Handles multipart mail, base64 / quoted-printable and non-UTF-8 character sets.
 */
class MimeMessage
{
    /** @var array<string,string> lower-case header name => decoded value (first occurrence) */
    public array $headers = [];
    public string $text = '';
    public string $html = '';

    public static function parse(string $raw): self
    {
        $m = new self;
        [$head, $body] = self::split($raw);
        $m->headers = self::headers($head);
        $m->walk($m->headers, $body, 0);

        return $m;
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /** Display name and address from a From/Reply-To header. */
    public static function address(string $value): array
    {
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $value, $x)) {
            return [trim($x[1]), strtolower(trim($x[2]))];
        }
        if (preg_match('/[\w.+\-]+@[\w\-]+(\.[\w\-]+)+/', $value, $x)) {
            return ['', strtolower($x[0])];
        }

        return ['', ''];
    }

    private function walk(array $headers, string $body, int $depth): void
    {
        if ($depth > 6) {
            return;
        }
        $type = strtolower($headers['content-type'] ?? 'text/plain');
        if (str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'] ?? '', $b)) {
            $q = preg_quote($b[1], '/');
            $inside = preg_split('/\r?\n--'.$q.'--/', "\n".$body, 2)[0]; // drop anything after the closing boundary
            foreach (preg_split('/\r?\n--'.$q.'[ \t]*\r?\n/', $inside."\n") as $i => $part) {
                if ($i === 0 || trim($part) === '') {
                    continue; // preamble
                }
                [$h, $p] = self::split($part);
                $this->walk(self::headers($h), $p, $depth + 1);
            }

            return;
        }
        if (str_starts_with($type, 'message/rfc822')) {
            [$h, $p] = self::split($body);
            $this->walk(self::headers($h), $p, $depth + 1);

            return;
        }
        if (str_contains(strtolower($headers['content-disposition'] ?? ''), 'attachment')) {
            return;
        }
        $isHtml = str_starts_with($type, 'text/html');
        if (! $isHtml && ! str_starts_with($type, 'text/plain')) {
            return;
        }
        $content = match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body), false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $charset = preg_match('/charset="?([\w\-]+)"?/i', $headers['content-type'] ?? '', $c) ? strtoupper($c[1]) : 'UTF-8';
        $content = self::toUtf8($content, $charset);
        if ($isHtml) {
            $this->html .= $content;
        } elseif ($this->text === '') {
            $this->text = $content;
        }
    }

    private static function split(string $raw): array
    {
        $raw = ltrim($raw, "\r\n");
        $pos = preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;

        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + strlen($m[0][0]))];
    }

    private static function headers(string $head): array
    {
        $head = preg_replace('/\r?\n[ \t]+/', ' ', $head); // unfold
        $out = [];
        foreach (preg_split('/\r?\n/', (string) $head) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            if (! isset($out[$k])) {
                $out[$k] = self::decodeHeader(trim($v));
            }
        }

        return $out;
    }

    private static function decodeHeader(string $v): string
    {
        if (! str_contains($v, '=?')) {
            return $v;
        }
        $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $d !== false ? $d : mb_decode_mimeheader($v);
    }

    private static function toUtf8(string $s, string $charset): string
    {
        if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
            return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        $c = @iconv($charset, 'UTF-8//IGNORE', $s);

        return $c !== false ? $c : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
}
