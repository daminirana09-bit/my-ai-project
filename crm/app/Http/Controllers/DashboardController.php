<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\LeadClash;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DashboardStats;
use App\Services\SalesReport;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SalesReport $report, DashboardStats $stats, AttendanceService $attendance)
    {
        $me = $request->user();
        $people = $me->isRep() ? collect([$me]) : User::active()->visibleTo($me)->where('role', '!=', 'admin')->orderBy('name')->get();
        $rows = $report->rows($people, now()->format('Y-m'));
        $ids = $me->visibleUserIds();

        return view('dashboard', [
            'k' => $stats->kpis($me),
            'rows' => $rows,
            'totals' => (object) [
                'target' => $rows->sum('target'), 'achieved' => $rows->sum('achieved'), 'pending_orders' => $rows->sum('pending_orders'),
                'remaining' => $rows->sum('remaining'), 'calls_today' => $rows->sum('calls_today'), 'calls_week' => $rows->sum('calls_week'),
            ],
            'team' => $me->canManage() ? $stats->teamReport($people) : collect(),
            'follow' => $stats->followUps($people),
            'latest' => $stats->latestLeads($me),
            'reminders' => $stats->reminders($me),
            'tasks' => $stats->taskCounts($me),
            'clashes' => $me->canManage()
                ? LeadClash::where('status', 'open')->with(['lead', 'existingOwner', 'otherUser'])
                    ->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('existing_owner_id', $ids)->orWhereIn('other_user_id', $ids)))
                    ->latest()->limit(5)->get()
                : collect(),
            'todayAttendance' => AttendanceDay::whereIn('user_id', $people->pluck('id'))->whereDate('date', today())->get()->keyBy('user_id'),
            'myToday' => AttendanceDay::where('user_id', $me->id)->whereDate('date', today())->first(),
        ]);
    }
}
