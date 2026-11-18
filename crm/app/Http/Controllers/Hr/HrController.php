<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\CompanySetting;
use App\Models\ExpenseClaim;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Hr\LeaveService;
use Illuminate\Http\Request;

/** "My HR" for everyone, the HR dashboard for managers, and the approvals list. */
class HrController extends Controller
{
    public function me(Request $request, LeaveService $leaves)
    {
        $me = $request->user()->load('profile');
        $month = now()->startOfMonth();

        return view('hr.me', [
            'me' => $me,
            'balances' => $leaves->balances($me, (int) now()->year),
            'types' => LeaveType::ordered()->where('active', true)->get(),
            'myLeaves' => LeaveRequest::with('type', 'decider:id,name')->where('user_id', $me->id)->latest('from_date')->limit(20)->get(),
            'claims' => ExpenseClaim::where('user_id', $me->id)->latest('date')->latest('id')->limit(30)->get(),
            'payslips' => Payslip::with('run')->where('user_id', $me->id)->whereHas('run', fn ($q) => $q->where('status', 'final'))->get()->sortByDesc(fn ($p) => $p->run->month)->values(),
            'holidays' => Holiday::where('date', '>=', today()->subDays(7))->orderBy('date')->limit(20)->get(),
            'month' => AttendanceDay::where('user_id', $me->id)->whereBetween('date', [$month->toDateString(), now()->endOfMonth()->toDateString().' 23:59:59'])
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'co' => CompanySetting::current(),
        ]);
    }

    public function dashboard(Request $request)
    {
        $me = $request->user();
        abort_unless($me->canManage(), 403);
        $people = User::active()->visibleTo($me)->with('profile')->orderBy('name')->get();
        $ids = $people->pluck('id');
        $today = AttendanceDay::whereIn('user_id', $ids)->whereDate('date', today())->pluck('status', 'user_id');
        $onLeave = LeaveRequest::with('user:id,name', 'type:id,code,name')->whereIn('user_id', $ids)->where('status', 'approved')
            ->where('from_date', '<=', today()->toDateString())->where('to_date', '>=', today()->toDateString())->get();
        $thisMonth = fn ($field) => $people->filter(fn ($u) => $u->profile?->{$field} && $u->profile->{$field}->month === now()->month)
            ->sortBy(fn ($u) => $u->profile->{$field}->day)->values();

        return view('hr.dashboard', [
            'people' => $people,
            'present' => $today->filter(fn ($s) => in_array($s, ['present', 'full_day', 'half_day', 'single_swipe'], true))->count(),
            'absent' => $today->filter(fn ($s) => $s === 'absent')->count(),
            'onLeave' => $onLeave,
            'pendingLeaves' => LeaveRequest::whereIn('user_id', $ids)->whereKeyNot(0)->where('user_id', '!=', $me->id)->where('status', 'pending')->count(),
            'pendingClaims' => ExpenseClaim::whereIn('user_id', $ids)->where('user_id', '!=', $me->id)->where('status', 'pending')->count(),
            'claimsMonth' => (float) ExpenseClaim::whereIn('user_id', $ids)->whereIn('status', ['approved', 'paid'])->where('date', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'birthdays' => $thisMonth('date_of_birth'),
            'anniversaries' => $thisMonth('date_of_joining')->filter(fn ($u) => $u->profile->date_of_joining->year < now()->year)->values(),
            'holidays' => Holiday::where('date', '>=', today())->orderBy('date')->limit(5)->get(),
            'notice' => $people->filter(fn ($u) => $u->profile?->status === 'notice')->values(),
            'missing' => $people->filter(fn ($u) => ! $u->profile || ! $u->profile->emp_code || ! $u->profile->date_of_joining)->count(),
        ]);
    }

    public function approvals(Request $request)
    {
        $me = $request->user();
        abort_unless($me->isAdmin() || $me->allows('approve_leaves'), 403);
        $ids = User::visibleTo($me)->when(! LeaveService::canApprove($me, $me), fn ($q) => $q->whereKeyNot($me->id))->pluck('id');

        return view('hr.approvals', [
            'leaves' => LeaveRequest::with('user:id,name', 'type')->whereIn('user_id', $ids)->where('status', 'pending')->orderBy('from_date')->get(),
            'claims' => ExpenseClaim::with('user:id,name')->whereIn('user_id', $ids)->where('status', 'pending')->orderBy('date')->get(),
            'recentLeaves' => LeaveRequest::with('user:id,name', 'type', 'decider:id,name')->whereIn('user_id', $ids)->whereIn('status', ['approved', 'rejected', 'cancelled'])
                ->where('updated_at', '>=', now()->subDays(45))->latest('updated_at')->limit(30)->get(),
            'recentClaims' => ExpenseClaim::with('user:id,name', 'decider:id,name')->whereIn('user_id', $ids)->whereIn('status', ['approved', 'rejected', 'paid'])
                ->where('updated_at', '>=', now()->subDays(45))->latest('updated_at')->limit(30)->get(),
            'leaves_service' => app(LeaveService::class),
        ]);
    }
}
