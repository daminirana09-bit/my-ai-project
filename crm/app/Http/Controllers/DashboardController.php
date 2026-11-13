<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\Lead;
use App\Models\LeadClash;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\SalesReport;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SalesReport $report, AttendanceService $attendance)
    {
        $me = $request->user();
        $month = now()->format('Y-m');
        $people = User::active()->visibleTo($me)->where('role', '!=', 'admin')->orderBy('name')->get();
        if ($me->isRep()) {
            $people = collect([$me]);
        }
        $rows = $report->rows($people, $month);

        $leadCounts = Lead::visibleTo($me)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $followUps = Lead::visibleTo($me)->whereNotIn('status', ['won', 'lost'])->whereDate('next_follow_up_on', '<=', today())
            ->with('owner:id,name')->orderBy('next_follow_up_on')->limit(8)->get();

        $clashes = $me->canManage()
            ? LeadClash::where('status', 'open')->with(['lead', 'existingOwner', 'otherUser'])
                ->when(! $me->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->whereIn('existing_owner_id', $me->visibleUserIds())->orWhereIn('other_user_id', $me->visibleUserIds())))
                ->latest()->limit(5)->get()
            : collect();

        $today = AttendanceDay::whereIn('user_id', $people->pluck('id'))->whereDate('date', today())->get()->keyBy('user_id');

        return view('dashboard', [
            'rows' => $rows,
            'totals' => (object) [
                'target' => $rows->sum('target'), 'achieved' => $rows->sum('achieved'), 'pending_orders' => $rows->sum('pending_orders'),
                'remaining' => $rows->sum('remaining'), 'calls_today' => $rows->sum('calls_today'), 'calls_week' => $rows->sum('calls_week'),
            ],
            'leadCounts' => $leadCounts,
            'followUps' => $followUps,
            'clashes' => $clashes,
            'todayAttendance' => $today,
            'people' => $people,
            'myToday' => $today->get($me->id) ?? AttendanceDay::where('user_id', $me->id)->whereDate('date', today())->first(),
            'nextPunch' => $attendance->nextPunchType($me),
        ]);
    }
}
