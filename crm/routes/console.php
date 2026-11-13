<?php

use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Closes a day's attendance: absent for no punch, "single swipe" for only one punch.
Artisan::command('attendance:close {date?}', function (AttendanceService $service) {
    $date = CarbonImmutable::parse($this->argument('date') ?? 'today');
    $n = $service->closeDay($date);
    $this->info("Attendance closed for {$date->toDateString()}: {$n} people.");
})->purpose('Mark absent and single-swipe days');

// On shared hosting one cron line runs this every minute: php artisan schedule:run
Schedule::command('attendance:close')->dailyAt('23:50');
Schedule::command('attendance:close yesterday')->dailyAt('00:10');
