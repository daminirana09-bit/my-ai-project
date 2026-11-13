<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Punch in / punch out and the day's status:
 * full day, half day, absent, single swipe (only one punch), weekly off, late.
 */
class AttendanceService
{
    public function lastPunchToday(User $user): ?AttendancePunch
    {
        return AttendancePunch::where('user_id', $user->id)
            ->whereBetween('punched_at', [today()->startOfDay(), today()->endOfDay()])
            ->latest('punched_at')->latest('id')->first();
    }

    /** The punch the user should make next: "in" or "out". */
    public function nextPunchType(User $user): string
    {
        return $this->lastPunchToday($user)?->type === 'in' ? 'out' : 'in';
    }

    public function punch(User $user, string $type, ?string $ip = null, ?CarbonInterface $at = null): AttendancePunch
    {
        if (! in_array($type, ['in', 'out'], true) || $type !== $this->nextPunchType($user)) {
            throw ValidationException::withMessages(['punch' => __('You are already punched :type.', ['type' => $type === 'in' ? __('in') : __('out')])]);
        }
        $punch = AttendancePunch::create(['user_id' => $user->id, 'type' => $type, 'punched_at' => $at ?? now(), 'ip' => $ip]);
        $this->recalculate($user, CarbonImmutable::parse($punch->punched_at)->startOfDay());

        return $punch;
    }

    /** Work out the day's minutes and status from the punches. Days set by hand are left alone. */
    public function recalculate(User $user, CarbonInterface $date): ?AttendanceDay
    {
        $c = CompanySetting::current();
        $date = CarbonImmutable::parse($date)->startOfDay();
        $day = $this->dayRow($user, $date);
        if ($day->manual) {
            return $day;
        }

        $punches = AttendancePunch::where('user_id', $user->id)
            ->whereBetween('punched_at', [$date, $date->endOfDay()])
            ->orderBy('punched_at')->orderBy('id')->get();

        $shift = $user->shift($c);
        $at = fn (string $time) => $date->setTimeFromTimeString($time);
        $isWeeklyOff = in_array($date->dayOfWeek, $c->weeklyOffDays(), true);

        if ($punches->isEmpty()) {
            $isPast = $date->lt(today()) || ($date->isToday() && now()->gt($at($shift['office_end'])));
            if (! $isPast && ! $isWeeklyOff) {
                return null; // the working day is not over yet
            }
            $day->fill(['first_in' => null, 'last_out' => null, 'worked_minutes' => 0, 'late' => false,
                'status' => $isWeeklyOff ? 'weekly_off' : 'absent'])->save();

            return $day;
        }

        // Pair every "in" with the next "out"; time inside the lunch break is not counted.
        $worked = 0;
        $open = null;
        foreach ($punches as $p) {
            $t = CarbonImmutable::parse($p->punched_at);
            if ($p->type === 'in') {
                $open = $t;
            } elseif ($open) {
                $worked += $this->minutesOutsideLunch($open, $t, $at($shift['lunch_start']), $at($shift['lunch_end']));
                $open = null;
            }
        }

        $firstIn = $punches->firstWhere('type', 'in')?->punched_at;
        $lastOut = $punches->where('type', 'out')->last()?->punched_at;
        $stillIn = $punches->last()->type === 'in';
        $late = $firstIn && CarbonImmutable::parse($firstIn)->gt($at($shift['office_start'])->addMinutes($c->late_grace_minutes));

        if ($stillIn || ! $firstIn) {
            // Only one punch. While the person may still be at work today, show "working now".
            $workingNow = $stillIn && $date->isToday() && now()->lt($at($shift['office_end'])->addHours(3));
            $status = $workingNow ? 'present' : 'single_swipe';
        } elseif ($worked >= $c->full_day_minutes) {
            $status = 'full_day';
        } elseif ($worked >= $c->half_day_minutes) {
            $status = 'half_day';
        } else {
            $status = 'absent';
        }

        $day->fill([
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'worked_minutes' => $worked,
            'status' => $status,
            'late' => (bool) $late,
        ])->save();

        return $day;
    }

    /** Matches on the calendar day, whatever time part the database keeps. */
    private function dayRow(User $user, CarbonInterface $date): AttendanceDay
    {
        return AttendanceDay::where('user_id', $user->id)->whereDate('date', $date->toDateString())->first()
            ?? new AttendanceDay(['user_id' => $user->id, 'date' => $date->toDateString()]);
    }

    private function minutesOutsideLunch(CarbonImmutable $in, CarbonImmutable $out, CarbonImmutable $lunchFrom, CarbonImmutable $lunchTo): int
    {
        $total = max(0, $in->diffInMinutes($out, false));
        $overlapStart = $in->max($lunchFrom);
        $overlapEnd = $out->min($lunchTo);
        $overlap = $overlapEnd->gt($overlapStart) ? $overlapStart->diffInMinutes($overlapEnd) : 0;

        return (int) round($total - $overlap);
    }

    /** End of day: mark everyone who never punched, and close days with only one punch. */
    public function closeDay(CarbonInterface $date): int
    {
        $n = 0;
        foreach (User::active()->get() as $user) {
            if ($this->recalculate($user, $date)) {
                $n++;
            }
        }

        return $n;
    }

    /** Admin sets a day by hand (e.g. approved leave, or a forgotten punch). */
    public function override(User $user, CarbonInterface $date, string $status, ?string $remark): AttendanceDay
    {
        $day = $this->dayRow($user, CarbonImmutable::parse($date));
        $day->fill(['status' => $status, 'manual' => true, 'remark' => $remark])->save();

        return $day;
    }
}
