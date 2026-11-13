<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class AttendanceTest extends CrmTestCase
{
    private function day(string $date, array $punches, $user = null): AttendanceDay
    {
        $user ??= $this->repA;
        $svc = app(AttendanceService::class);
        foreach ($punches as [$type, $time]) {
            Carbon::setTestNow(CarbonImmutable::parse("$date $time"));
            $svc->punch($user, $type);
        }
        Carbon::setTestNow(CarbonImmutable::parse("$date 23:55"));
        $svc->closeDay(CarbonImmutable::parse($date));

        return AttendanceDay::where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_day_lunch_not_counted(): void
    {
        // 09:25 → 18:35 = 550 min, minus 30 min lunch = 520 min
        $d = $this->day('2026-11-12', [['in', '09:25'], ['out', '18:35']]);
        $this->assertSame('full_day', $d->status);
        $this->assertSame(520, $d->worked_minutes);
        $this->assertFalse($d->late);
    }

    public function test_half_day_and_late(): void
    {
        $d = $this->day('2026-11-12', [['in', '14:00'], ['out', '18:30']]);
        $this->assertSame('half_day', $d->status);
        $this->assertTrue($d->late);
    }

    public function test_single_swipe_when_no_punch_out(): void
    {
        $d = $this->day('2026-11-12', [['in', '09:30']]);
        $this->assertSame('single_swipe', $d->status);
    }

    public function test_absent_when_no_punch_and_weekly_off_on_sunday(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-16 00:10'));
        app(AttendanceService::class)->closeDay(CarbonImmutable::parse('2026-11-14')); // Saturday
        app(AttendanceService::class)->closeDay(CarbonImmutable::parse('2026-11-15')); // Sunday
        $this->assertSame('absent', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', '2026-11-14')->value('status'));
        $this->assertSame('weekly_off', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', '2026-11-15')->value('status'));
    }

    public function test_lunch_punch_out_and_back_in(): void
    {
        $d = $this->day('2026-11-12', [['in', '09:30'], ['out', '13:30'], ['in', '14:00'], ['out', '18:30']]);
        $this->assertSame(510, $d->worked_minutes); // 9:30–13:30 + 14:00–18:30
        $this->assertSame('full_day', $d->status);
    }

    public function test_personal_timing_changes_late_mark(): void
    {
        $this->repB->update(['office_start' => '11:00', 'office_end' => '20:00']);
        $d = $this->day('2026-11-12', [['in', '11:05'], ['out', '20:00']], $this->repB);
        $this->assertFalse($d->late);
        $this->assertSame('full_day', $d->status);
    }

    public function test_cannot_punch_in_twice(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-12 09:30'));
        $this->actingAs($this->repA)->post('/attendance/punch', ['type' => 'in'])->assertRedirect();
        $this->actingAs($this->repA)->post('/attendance/punch', ['type' => 'in'])->assertSessionHasErrors('punch');
    }

    public function test_admin_override_is_kept(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-12 20:00'));
        $this->actingAs($this->manager)->post("/attendance/people/{$this->repA->id}/day", ['date' => '2026-11-12', 'status' => 'leave', 'remark' => 'Approved'])->assertRedirect();
        app(AttendanceService::class)->closeDay(CarbonImmutable::parse('2026-11-12'));
        $this->assertSame('leave', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', '2026-11-12')->value('status'));
        $this->actingAs($this->repA)->post("/attendance/people/{$this->repB->id}/day", ['date' => '2026-11-12', 'status' => 'leave'])->assertForbidden();
    }
}
