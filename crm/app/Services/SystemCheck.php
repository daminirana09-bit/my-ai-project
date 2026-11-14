<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\User;
use App\Models\WhatsappNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Checks the installation after it goes live on hosting. Shown to the admin and via `php artisan crm:check`. */
class SystemCheck
{
    /** @return array<int, array{0:string,1:string,2:string}> [status ok|warn|bad, check, detail] */
    public function run(): array
    {
        $r = [];
        $add = function (string $s, string $name, string $detail = '') use (&$r) {
            $r[] = [$s, $name, $detail];
        };

        $add(version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'bad', 'PHP 8.3 or newer', PHP_VERSION);
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'gd', 'zip', 'xml', 'intl', 'fileinfo', 'curl'] as $ext) {
            $add(extension_loaded($ext) ? 'ok' : ($ext === 'intl' ? 'warn' : 'bad'), "PHP extension: $ext");
        }
        try {
            DB::select('select 1');
            $add('ok', 'Database connection', config('database.default'));
        } catch (\Throwable $e) {
            $add('bad', 'Database connection', class_basename($e));
        }
        $add(config('app.debug') ? 'bad' : 'ok', 'APP_DEBUG is off', config('app.debug') ? 'Set APP_DEBUG=false in .env' : '');
        $add(app()->environment('production') ? 'ok' : 'warn', 'APP_ENV is production', app()->environment());
        $add(str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'warn', 'APP_URL uses https', (string) config('app.url'));
        $add(config('session.secure') ? 'ok' : 'warn', 'Cookies only over HTTPS (SESSION_SECURE_COOKIE)');
        $add(config('session.encrypt') ? 'ok' : 'warn', 'Sessions encrypted (SESSION_ENCRYPT)');
        foreach (['storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $add(is_writable(base_path($dir)) ? 'ok' : 'bad', "Writable: $dir");
        }
        $env = base_path('.env');
        if (is_file($env)) {
            $perm = substr(sprintf('%o', fileperms($env)), -3);
            $add(in_array($perm, ['600', '640', '400', '440'], true) ? 'ok' : 'warn', '.env readable only by the owner', 'chmod 600 .env (now '.$perm.')');
        }
        $last = Cache::get('crm:cron_last_run');
        $add($last && now()->diffInMinutes($last, true) < 5 ? 'ok' : 'bad', 'Cron runs every minute', $last ? 'last run '.\Illuminate\Support\Carbon::parse($last)->diffForHumans() : 'never: add the cron job');
        $pending = DB::table('jobs')->count();
        $add($pending < 50 ? 'ok' : 'warn', 'Background queue', $pending.' waiting');
        $c = CompanySetting::current();
        $add($c?->wa('access_token') && $c?->wa('app_secret') ? 'ok' : 'warn', 'WhatsApp connected', $c?->wa('access_token') ? '' : 'WhatsApp settings');
        $add(WhatsappNumber::where('active', true)->exists() ? 'ok' : 'warn', 'WhatsApp numbers added', WhatsappNumber::where('active', true)->count().' active');
        $no2fa = User::active()->whereIn('role', ['admin', 'manager'])->whereNull('two_factor_secret')->count();
        $add($no2fa === 0 ? 'ok' : 'warn', 'Admins and managers use 2-step login', $no2fa ? $no2fa.' without it' : '');
        $add(filled(config('crm.allowed_ips')) ? 'ok' : 'warn', 'CRM limited to office IPs (CRM_ALLOWED_IPS)', filled(config('crm.allowed_ips')) ? '' : 'Optional, recommended');

        return $r;
    }
}
