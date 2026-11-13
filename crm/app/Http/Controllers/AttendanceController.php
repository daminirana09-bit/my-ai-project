<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function punch(Request $request)
    {
        $type = $request->validate(['type' => ['required', Rule::in(['in', 'out'])]])['type'];
        $p = $this->service->punch($request->user(), $type, $request->ip());

        return back()->with('ok', __($type === 'in' ? 'Punched in at :time.' : 'Punched out at :time.', ['time' => $p->punched_at->format('h:i A')]));
    }

    private function month(Request $request): CarbonImmutable
    {
        $m = (string) $request->query('month', now()->format('Y-m'));

        return CarbonImmutable::createFromFormat('Y-m-d', (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->format('Y-m')).'-01')->startOfDay();
    }

    /** One person's month: every day with punches, hours and status. */
    public function mine(Request $request)
    {
        return $this->personMonth($request, $request->user());
    }

    public function person(Request $request, User $user)
    {
        abort_unless(in_array($user->id, User::visibleTo($request->user())->pluck('id')->all(), true), 404);

        return $this->personMonth($request, $user);
    }

    private function personMonth(Request $request, User $user)
    {
        $start = $this->month($request);
        $end = $start->endOfMonth();
        $days = AttendanceDay::where('user_id', $user->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->keyBy(fn ($d) => $d->date->toDateString());
        $punches = AttendancePunch::where('user_id', $user->id)->whereBetween('punched_at', [$start, $end->endOfDay()])->orderBy('punched_at')->get()
            ->groupBy(fn ($p) => $p->punched_at->toDateString());

        return view('attendance.person', [
            'person' => $user, 'start' => $start, 'end' => $end, 'days' => $days, 'punches' => $punches,
            'shift' => $user->shift(CompanySetting::current()),
            'summary' => $days->countBy('status'),
        ]);
    }

    /** Today's board for admin and managers. */
    public function today(Request $request)
    {
        $me = $request->user();
        $people = User::active()->visibleTo($me)->orderBy('name')->get();
        foreach ($people as $p) {
            $this->service->recalculate($p, today()); // keeps "working now" and "single swipe" up to date
        }
        $days = AttendanceDay::whereIn('user_id', $people->pluck('id'))->whereDate('date', today())->get()->keyBy('user_id');

        return view('attendance.today', ['people' => $people, 'days' => $days, 'company' => CompanySetting::current()]);
    }

    /** Month register: everyone × every day. */
    public function register(Request $request)
    {
        $me = $request->user();
        $start = $this->month($request);
        $end = $start->endOfMonth();
        $people = User::active()->visibleTo($me)->orderBy('name')->get();
        $days = AttendanceDay::whereIn('user_id', $people->pluck('id'))->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->groupBy('user_id')->map(fn ($g) => $g->keyBy(fn ($d) => $d->date->toDateString()));

        return view('attendance.register', ['people' => $people, 'days' => $days, 'start' => $start, 'end' => $end]);
    }

    public function override(Request $request, User $user)
    {
        $me = $request->user();
        abort_unless($me->canManage() && in_array($user->id, User::visibleTo($me)->pluck('id')->all(), true), 404);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['full_day', 'half_day', 'absent', 'leave', 'weekly_off', 'auto'])],
            'remark' => ['nullable', 'string', 'max:150'],
        ]);
        if ($data['status'] === 'auto') {
            AttendanceDay::where('user_id', $user->id)->whereDate('date', $data['date'])->update(['manual' => false]);
            $this->service->recalculate($user, CarbonImmutable::parse($data['date']));
        } else {
            $this->service->override($user, CarbonImmutable::parse($data['date']), $data['status'], $data['remark'] ?? null);
        }
        AuditLog::record('attendance.override', "user:{$user->id}", $data);

        return back()->with('ok', __('Attendance updated.'));
    }
}
