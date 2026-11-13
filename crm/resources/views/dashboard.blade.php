@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
@php $me = auth()->user(); $statuses = \App\Models\Lead::STATUSES; @endphp
<div class="head">
  <div><h1>{{ __('Hello, :name', ['name' => $me->name]) }}</h1>
    <p>{{ $me->canManage() ? __('Your team for :month. Only you and other managers see these numbers.', ['month' => now()->translatedFormat('F Y')]) : __('Your work for :month.', ['month' => now()->translatedFormat('F Y')]) }}</p></div>
  <div class="row"><a class="btn" href="{{ route('leads.import') }}">{{ __('Import leads') }}</a><a class="btn p" href="{{ route('leads.create') }}">{{ __('New lead') }}</a></div>
</div>

<div class="grid g4">
  <div class="card kpi"><div class="l">{{ __('Target this month') }}</div><div class="v">{{ inr($totals->target) }}</div>
    <meter min="0" max="{{ max($totals->target, 1) }}" value="{{ $totals->achieved }}" low="{{ $totals->target * .5 }}" high="{{ $totals->target * .8 }}" optimum="{{ $totals->target }}"></meter></div>
  <div class="card kpi"><div class="l">{{ __('Sales done') }}</div><div class="v">{{ inr($totals->achieved) }}</div><div class="s">{{ $totals->target > 0 ? round($totals->achieved / $totals->target * 100).'%' : '—' }} · {{ __('pending orders') }} {{ inr($totals->pending_orders) }}</div></div>
  <div class="card kpi"><div class="l">{{ __('Still to achieve') }}</div><div class="v">{{ inr($totals->remaining) }}</div><div class="s">{{ __(':days days left', ['days' => (int) today()->diffInDays(today()->endOfMonth()->startOfDay())]) }}</div></div>
  <div class="card kpi"><div class="l">{{ __('Calls') }}</div><div class="v num">{{ $totals->calls_today }}</div><div class="s">{{ __('today') }} · {{ $totals->calls_week }} {{ __('this week') }}</div></div>
</div>

@if($me->canManage())
<div class="card">
  <div class="ch"><h2>{{ __('Team: target, sales and calls') }}</h2><a class="btn sm" href="{{ route('targets.index') }}">{{ __('Set targets') }}</a></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Target') }}</th><th class="r">{{ __('Sales done') }}</th><th class="r">{{ __('Pending') }}</th><th>{{ __('Progress') }}</th><th class="r">{{ __('Calls today') }}</th><th class="r">{{ __('Calls this week') }}</th><th class="r">{{ __('Open leads') }}</th><th class="r">{{ __('Follow-ups due') }}</th><th>{{ __('Today') }}</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
      @php $d = $todayAttendance->get($r->user->id); @endphp
      <tr>
        <td><b>{{ $r->user->name }}</b><span class="sub">{{ __(\App\Models\User::ROLES[$r->user->role]) }}</span></td>
        <td class="r num">{{ $r->target ? inr($r->target) : '—' }}</td>
        <td class="r num">{{ inr($r->achieved) }}</td>
        <td class="r num">{{ inr($r->remaining) }}<span class="sub">{{ __('orders') }} {{ inr($r->pending_orders) }}</span></td>
        <td><meter min="0" max="{{ max($r->target, 1) }}" value="{{ $r->achieved }}" low="{{ $r->target * .5 }}" high="{{ $r->target * .8 }}" optimum="{{ $r->target }}"></meter><span class="sub">{{ $r->percent !== null ? $r->percent.'%' : __('No target') }}</span></td>
        <td class="r num">{{ $r->calls_today }}</td>
        <td class="r num">{{ $r->calls_week }}</td>
        <td class="r num">{{ $r->leads_open }}</td>
        <td class="r num">{{ $r->follow_ups_due ?: '—' }}</td>
        <td>@if($d)<span class="pill {{ $d->status }}">{{ __(\App\Models\AttendanceDay::STATUSES[$d->status]) }}</span>@if($d->late) <span class="pill bad">{{ __('Late') }}</span>@endif @else<span class="pill">{{ __('Not punched') }}</span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="10" class="muted">{{ __('No salespeople yet. Add them under Users.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</div>
@endif

<div class="grid g21">
  <div class="card">
    <div class="ch"><h2>{{ __('Follow-ups due') }}</h2><a class="btn sm" href="{{ route('leads.index', ['due' => 1]) }}">{{ __('See all') }}</a></div>
    <div class="list">
      @forelse($followUps as $l)
        <div class="item"><span class="bar {{ $l->next_follow_up_on->isPast() && ! $l->next_follow_up_on->isToday() ? 'bad' : 'warn' }}"></span>
          <div><b>{{ $l->firm_name }}</b><small>{{ $l->contact_name }} · {{ $l->city }} · {{ $l->next_follow_up_on->format('d M') }}@if($me->canManage()) · {{ $l->owner?->name }}@endif</small></div>
          <a class="btn sm" href="{{ route('leads.show', $l) }}">{{ __('Open') }}</a></div>
      @empty
        <p class="muted">{{ __('Nothing due today.') }}</p>
      @endforelse
    </div>
  </div>
  <div class="grid">
    <div class="card"><h2>{{ __('Leads by status') }}</h2>
      <div class="list">@foreach($statuses as $k => $label)<div class="item"><span class="bar"></span><div><a href="{{ route('leads.index', ['status' => $k]) }}">{{ __($label) }}</a></div><b class="num">{{ $leadCounts[$k] ?? 0 }}</b></div>@endforeach</div>
    </div>
    @if($me->canManage())
    <div class="card kpi {{ $clashes->isNotEmpty() ? 'alert' : '' }}"><div class="ch"><h2>{{ __('Lead clashes') }}</h2><a class="btn sm" href="{{ route('clashes.index') }}">{{ __('Resolve') }}</a></div>
      @forelse($clashes as $c)
        <p class="small"><b>{{ $c->lead?->firm_name }}</b>: {{ $c->existingOwner?->name }} ↔ {{ $c->otherUser?->name }}</p>
      @empty
        <p class="muted">{{ __('No clashes.') }}</p>
      @endforelse
    </div>
    @endif
    <div class="card"><h2>{{ __('My attendance today') }}</h2>
      @if($myToday)
        <p><span class="pill {{ $myToday->status }}">{{ __(\App\Models\AttendanceDay::STATUSES[$myToday->status]) }}</span> @if($myToday->late)<span class="pill bad">{{ __('Late') }}</span>@endif</p>
        <p class="small muted">{{ __('In') }} {{ $myToday->first_in?->format('h:i A') ?? '—' }} · {{ __('Out') }} {{ $myToday->last_out?->format('h:i A') ?? '—' }} · {{ hm($myToday->worked_minutes) }}</p>
      @else
        <p class="muted">{{ __('You have not punched in today.') }}</p>
      @endif
    </div>
  </div>
</div>
@endsection
