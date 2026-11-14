@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
@php $me = auth()->user(); $S = \App\Models\Task::STATUSES; @endphp
<div class="head">
  <div><h1>{{ __('Hello, :name', ['name' => $me->name]) }}</h1>
    <p>{{ now()->translatedFormat('l, d F Y') }} · {{ $me->canManage() ? __('Your team at a glance. Only you and other managers see these numbers.') : __('Your own work at a glance.') }}</p></div>
  <div class="row">
    @if($me->allows('import_leads'))<a class="btn" href="{{ route('leads.import') }}"><x-icon name="upload"/>{{ __('Import leads') }}</a>@endif
    <a class="btn p" href="{{ route('leads.create') }}"><x-icon name="plus"/>{{ __('New lead') }}</a>
  </div>
</div>

{{-- Six headline numbers, each opens the matching list --}}
<section class="kpis" aria-label="{{ __('Summary') }}">
  <a class="card kpi" href="{{ route('leads.index') }}"><div class="top"><span class="l">{{ __('Leads') }}</span><span class="chip-ic t-blue"><x-icon name="leads"/></span></div><span class="v">{{ number_format($k->leads) }}</span><span class="s">{{ __('all leads') }}</span></a>
  <a class="card kpi" href="{{ route('customers.index') }}"><div class="top"><span class="l">{{ __('Customers') }}</span><span class="chip-ic t-green"><x-icon name="customers"/></span></div><span class="v">{{ number_format($k->customers) }}</span><span class="s">{{ __('leads won') }}</span></a>
  <a class="card kpi" href="{{ route('targets.index') }}"><div class="top"><span class="l">{{ __('Orders') }}</span><span class="chip-ic t-violet"><x-icon name="cart"/></span></div><span class="v">{{ number_format($k->orders) }}</span><span class="s">{{ __('this month') }}</span></a>
  <a class="card kpi" href="{{ route('targets.index') }}"><div class="top"><span class="l">{{ __('Sales') }}</span><span class="chip-ic t-cyan"><x-icon name="rupee"/></span></div><span class="v">{{ inr($k->sales) }}</span><span class="s">{{ __('confirmed this month') }}</span></a>
  <a class="card kpi {{ $k->stale ? 'alert' : '' }}" href="{{ route('leads.index', ['stale' => 1]) }}"><div class="top"><span class="l">{{ __('No update in :n days', ['n' => \App\Models\Lead::STALE_DAYS]) }}</span><span class="chip-ic t-amber"><x-icon name="moon"/></span></div><span class="v">{{ number_format($k->stale) }}</span><span class="s">{{ __('open leads going cold') }}</span></a>
  <a class="card kpi" href="{{ route('leads.index', ['status' => 'lost']) }}"><div class="top"><span class="l">{{ __('Dead') }}</span><span class="chip-ic t-red"><x-icon name="dead"/></span></div><span class="v">{{ number_format($k->dead) }}</span><span class="s">{{ __('leads lost') }}</span></a>
</section>

{{-- Target for this month --}}
<section class="card">
  <div class="target-strip">
    <div><span class="l">{{ __('Target this month') }}</span><div class="v">{{ inr($totals->target) }}</div></div>
    <div><span class="l">{{ __('Sales done') }}</span><div class="v">{{ inr($totals->achieved) }}</div></div>
    <div><span class="l">{{ __('Still to achieve') }}</span><div class="v">{{ inr($totals->remaining) }}</div></div>
    <div><span class="l">{{ __('Calls') }}</span><div class="v num">{{ $totals->calls_today }} <span class="small muted">{{ __('today') }} · {{ $totals->calls_week }} {{ __('this week') }}</span></div></div>
    <div><span class="l">{{ $totals->target > 0 ? round($totals->achieved / $totals->target * 100).'%' : __('No target') }}</span>
      <meter min="0" max="{{ max($totals->target, 1) }}" value="{{ $totals->achieved }}" low="{{ $totals->target * .5 }}" high="{{ $totals->target * .8 }}" optimum="{{ $totals->target }}"></meter>
      <span class="small muted">{{ __('pending orders') }} {{ inr($totals->pending_orders) }}</span></div>
  </div>
</section>

<div class="grid g21">
  <div class="grid">
    @if($me->canManage())
    <section class="card flush">
      <div class="ch"><h2>{{ __('Team report') }}</h2><a class="btn sm p" href="{{ route('leads.create') }}"><x-icon name="plus"/>{{ __('Add new lead') }}</a></div>
      <div class="tbl scroll"><table>
        <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Total') }}</th><th class="r">{{ __('Mature') }}</th><th class="r">{{ __('Hot') }}</th><th class="r">{{ __('Warm') }}</th><th class="r">{{ __('Cold') }}</th><th class="r">{{ __('Follow-up') }}</th><th class="r">{{ __('Dead') }}</th><th>{{ __('Target') }}</th></tr></thead>
        <tbody>
        @forelse($team as $t)
          @php $r = $rows->firstWhere('user.id', $t->user->id); @endphp
          <tr>
            <td><a href="{{ route('leads.index', ['owner' => $t->user->id]) }}"><b>{{ $t->user->name }}</b></a><span class="sub">{{ __(\App\Models\User::ROLES[$t->user->role]) }}</span></td>
            <td class="r num">{{ $t->total }}</td><td class="r num">{{ $t->mature }}</td><td class="r num">{{ $t->hot }}</td><td class="r num">{{ $t->warm }}</td>
            <td class="r num">{{ $t->cold }}</td><td class="r num">{{ $t->follow_up }}</td><td class="r num">{{ $t->dead }}</td>
            <td><meter min="0" max="{{ max($r->target ?? 0, 1) }}" value="{{ $r->achieved ?? 0 }}" low="{{ ($r->target ?? 0) * .5 }}" high="{{ ($r->target ?? 0) * .8 }}" optimum="{{ $r->target ?? 0 }}"></meter><span class="sub">{{ ($r->percent ?? null) !== null ? $r->percent.'%' : __('No target') }}</span></td>
          </tr>
        @empty
          <tr><td colspan="9" class="empty">{{ __('No salespeople yet. Add them under Users.') }}</td></tr>
        @endforelse
        </tbody>
      </table></div>
    </section>
    @endif

    <section class="card flush">
      <div class="ch"><h2>{{ __('Follow-ups') }}</h2><a class="btn sm" href="{{ route('leads.index', ['due' => 1]) }}">{{ __('See due') }}</a></div>
      <div class="tbl scroll"><table>
        <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Pending') }}</th><th class="r">{{ __('Overdue') }}</th><th class="r">{{ __('Today') }}</th><th class="r">{{ __('Next 7 days') }}</th><th class="r">{{ __('Updated today') }}</th></tr></thead>
        <tbody>
        @foreach($follow as $f)
          <tr>
            <td>{{ $f->user->name }}</td>
            <td class="r num">{{ $f->total }}</td>
            <td class="r num">@if($f->overdue)<span class="pill bad">{{ $f->overdue }}</span>@else 0 @endif</td>
            <td class="r num">@if($f->today)<span class="pill warn">{{ $f->today }}</span>@else 0 @endif</td>
            <td class="r num">{{ $f->upcoming }}</td>
            <td class="r num">{{ $f->updated_today }}</td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
    </section>

    @if($me->canManage() && $clashes->isNotEmpty())
    <section class="card kpi alert">
      <div class="ch"><h2>{{ __('Lead clashes') }}</h2><a class="btn sm p" href="{{ route('clashes.index') }}">{{ __('Resolve') }}</a></div>
      <div class="list">@foreach($clashes as $c)<div class="item"><span class="bar bad"></span><div><b>{{ $c->lead?->firm_name }}</b><small>{{ $c->existingOwner?->name }} ↔ {{ $c->otherUser?->name }} · {{ $c->created_at->diffForHumans() }}</small></div><span></span></div>@endforeach</div>
    </section>
    @endif
  </div>

  <aside class="grid">
    <section class="card">
      <div class="ch"><h2>{{ __('Reminders') }}</h2><span class="pill {{ $reminders->where('overdue', true)->count() ? 'bad' : 'accent' }}">{{ $reminders->count() }}</span></div>
      <div class="list">
        @forelse($reminders as $r)
          <a class="item" href="{{ $r->url }}"><span class="bar {{ $r->overdue ? 'bad' : 'warn' }}"></span><div><b>{{ $r->title }}</b><small>{{ $r->kind === 'task' ? __('Task') : __('Follow-up') }} · {{ $r->date->isToday() ? __('today') : $r->date->format('d M') }}</small></div><span></span></a>
        @empty
          <p class="empty">{{ __('Nothing due today.') }}</p>
        @endforelse
      </div>
    </section>

    <section class="card">
      <h2>{{ __('Lead mix') }}</h2>
      <div class="tiles">
        <a class="tile mature" href="{{ route('leads.index', ['status' => 'won']) }}"><span class="l">{{ __('Mature') }}</span><span class="v">{{ $k->mature }}</span></a>
        <a class="tile hot" href="{{ route('leads.index', ['temperature' => 'hot']) }}"><span class="l">{{ __('Hot') }}</span><span class="v">{{ $k->hot }}</span></a>
        <a class="tile warm" href="{{ route('leads.index', ['temperature' => 'warm']) }}"><span class="l">{{ __('Warm') }}</span><span class="v">{{ $k->warm }}</span></a>
        <a class="tile cold" href="{{ route('leads.index', ['temperature' => 'cold']) }}"><span class="l">{{ __('Cold') }}</span><span class="v">{{ $k->cold }}</span></a>
        <a class="tile" href="{{ route('leads.index', ['due' => 1]) }}"><span class="l">{{ __('Follow-up') }}</span><span class="v">{{ $k->follow_up }}</span></a>
        <a class="tile" href="{{ route('leads.index', ['status' => 'lost']) }}"><span class="l">{{ __('Dead') }}</span><span class="v">{{ $k->dead }}</span></a>
      </div>
    </section>

    <section class="card">
      <div class="ch"><h2>{{ __('Tasks') }}</h2><a class="btn sm" href="{{ route('tasks.index') }}">{{ __('Open') }}</a></div>
      <div class="tiles">
        @foreach($S as $key => $label)
          <a class="tile" href="{{ route('tasks.index', ['status' => $key]) }}"><span class="l">{{ __($label) }}</span><span class="v">{{ $tasks[$key]['self'] }}</span>@if($me->canManage())<span class="s">{{ __('Team') }}: {{ $tasks[$key]['team'] }}</span>@else<span class="s">{{ __('Mine') }}</span>@endif</a>
        @endforeach
      </div>
    </section>

    <section class="card">
      <div class="ch"><h2>{{ __('Latest leads') }}</h2><a class="btn sm" href="{{ route('leads.index') }}">{{ __('See all') }}</a></div>
      <div class="list">
        @forelse($latest as $l)
          <a class="item" href="{{ route('leads.show', $l) }}"><span class="bar info"></span><div><b>{{ $l->firm_name }}</b><small><span class="mono">{{ $l->maskedPhone() }}</span> · {{ $l->created_at->diffForHumans() }}@if($me->canManage()) · {{ $l->owner?->name }}@endif</small></div><span class="pill {{ $l->status }}">{{ __(\App\Models\Lead::STATUSES[$l->status]) }}</span></a>
        @empty
          <p class="empty">{{ __('No leads found.') }}</p>
        @endforelse
      </div>
    </section>

    <section class="card">
      <h2>{{ __('My attendance today') }}</h2>
      @if($myToday)
        <p><span class="pill {{ $myToday->status }}">{{ __(\App\Models\AttendanceDay::STATUSES[$myToday->status]) }}</span> @if($myToday->late)<span class="pill bad">{{ __('Late') }}</span>@endif</p>
        <p class="small muted">{{ __('In') }} {{ $myToday->first_in?->format('h:i A') ?? '—' }} · {{ __('Out') }} {{ $myToday->last_out?->format('h:i A') ?? '—' }} · {{ hm($myToday->worked_minutes) }}</p>
      @else
        <p class="muted">{{ __('You have not punched in today.') }}</p>
      @endif
      @if($me->canManage())
        <p class="small muted">{{ __(':n of :m people punched in today.', ['n' => $todayAttendance->whereNotIn('status', ['absent', 'weekly_off'])->count(), 'm' => $rows->count()]) }} <a href="{{ route('attendance.today') }}">{{ __('View') }}</a></p>
      @endif
    </section>
  </aside>
</div>
@endsection
