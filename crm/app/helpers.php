<?php

if (! function_exists('inr')) {
    /** ₹ in Indian style: ₹4.85 L, ₹1.20 Cr, ₹12,500 */
    function inr($amount, bool $short = true): string
    {
        $n = (float) $amount;
        if ($short && abs($n) >= 1e7) {
            return '₹'.number_format($n / 1e7, 2).' Cr';
        }
        if ($short && abs($n) >= 1e5) {
            return '₹'.number_format($n / 1e5, 2).' L';
        }
        $whole = (string) round(abs($n));
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $formatted = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3 : $last3;

        return ($n < 0 ? '-' : '').'₹'.$formatted;
    }
}

if (! function_exists('hm')) {
    /** 485 → "8h 05m" */
    function hm(?int $minutes): string
    {
        if (! $minutes) {
            return '—';
        }

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}

if (! function_exists('t12')) {
    /** "14:30:00" → "2:30 PM" */
    function t12(?string $time): string
    {
        return $time ? date('g:i A', strtotime($time)) : '—';
    }
}

if (! function_exists('asset_v')) {
    /**
     * Link to a file in public/ with a short fingerprint of its content (css/app.css?v=3f9a…),
     * so after an update every browser and the hosting CDN load the new file at once.
     */
    function asset_v(string $path): string
    {
        static $seen = [];
        $file = public_path($path);
        $seen[$path] ??= is_file($file) ? substr(md5_file($file), 0, 10) : '0';

        return asset($path).'?v='.$seen[$path];
    }
}
