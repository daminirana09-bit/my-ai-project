<?php

use App\Services\AttendanceService;
use App\Services\DailyAutomations;
use App\Services\SystemCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Closes a day's attendance: absent for no punch, "single swipe" for only one punch.
Artisan::command('attendance:close {date?}', function (AttendanceService $service) {
    $date = CarbonImmutable::parse($this->argument('date') ?? 'today');
    $n = $service->closeDay($date);
    $this->info("Attendance closed for {$date->toDateString()}: {$n} people.");
})->purpose('Mark absent and single-swipe days');

Artisan::command('crm:automations', function (DailyAutomations $a) {
    $r = $a->run();
    $this->info("Follow-up tasks created: {$r['stale_tasks']}. Overdue alerts sent: {$r['overdue_alerts']}.");
})->purpose('Daily automations: stale-lead tasks and overdue follow-up alerts');

Artisan::command('crm:check', function (SystemCheck $check) {
    foreach ($check->run() as [$s, $name, $detail]) {
        $this->line(str_pad(strtoupper($s), 5).' '.$name.($detail !== '' ? '  ('.$detail.')' : ''));
    }
})->purpose('Check this installation (PHP, database, cron, security settings, WhatsApp)');

Artisan::command('leads:pull', function (\App\Services\Leads\Puller $puller) {
    foreach (\App\Models\LeadIntegration::where('enabled', true)->get() as $i) {
        if (! $i->canPull()) {
            continue;
        }
        $r = $puller->pull($i);
        $this->line($i->name().': '.($r['ok'] ? 'new '.$r['counts']['created'].', already in CRM '.$r['counts']['existing'] : $r['message']));
    }
})->purpose('Fetch new leads from IndiaMART, TradeIndia and portal API links');

// One cron line on the hosting runs all of this every minute: php artisan schedule:run
// Shared hosting (Hostinger and others) turns off proc_open, so every task runs inside the same PHP
// process with Artisan::call, never as a separate process. Each task notes when it last ran and
// whether it worked, for System check.
$task = fn (string $name, string $command, array $args = []) => function () use ($name, $command, $args) {
    try {
        $code = Artisan::call($command, $args);
        Cache::put('crm:task:'.$name, ['at' => now()->toIso8601String(), 'ok' => $code === 0, 'error' => $code === 0 ? null : 'exit code '.$code], now()->addDays(3));
    } catch (Throwable $e) {
        Cache::put('crm:task:'.$name, ['at' => now()->toIso8601String(), 'ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200)], now()->addDays(3));
        report($e);
    }
};
Schedule::call(fn () => Cache::put('crm:cron_last_run', now()->toIso8601String(), now()->addDay()))->everyMinute()->name('cron-heartbeat');
Schedule::call($task('attendance-close', 'attendance:close'))->dailyAt('23:50')->name('attendance-close');
Schedule::call($task('attendance-close-yesterday', 'attendance:close', ['date' => 'yesterday']))->dailyAt('00:10')->name('attendance-close-yesterday');
Schedule::call($task('automations', 'crm:automations'))->dailyAt('09:05')->name('automations');
Schedule::call($task('leads-pull', 'leads:pull'))->everyTenMinutes()->name('leads-pull')->withoutOverlapping(15);
// Last, because it may work for up to 50 seconds (WhatsApp campaigns and other background work).
Schedule::call($task('queue', 'queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3]))->everyMinute()->name('queue')->withoutOverlapping(5);
