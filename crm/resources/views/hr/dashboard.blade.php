@extends('layouts.app')
@section('title', __('HR dashboard'))
@section('content')
<div class="head"><div><h1>{{ __('HR dashboard') }}</h1><p>{{ auth()->user()->isAdmin() ? __('Everyone in the company.') : __('Your team.') }} {{ now()->translatedFormat('l, d M Y') }}</p></div>
  @if(auth()->user()->isAdmin() || auth()->user()->allows('approve_leaves'))<a class="btn p" href="{{ route('hr.approvals') }}"><x-icon name="check"/>{{ __('Approvals') }} ({{ $pendingLeaves + $pendingClaims }})</a>@endif</div>
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('People') }}</span><span class="v">{{ $people->count() }}</span></div>
  <div class="card kpi"><span class="l">{{ __('At work today') }}</span><span class="v">{{ $present }}</span></div>
  <div class="card kpi"><span class="l">{{ __('On leave today') }}</span><span class="v">{{ $onLeave->count() }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Leave waiting') }}</span><span class="v">{{ $pendingLeaves }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Claims waiting') }}</span><span class="v">{{ $pendingClaims }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Claims this month') }}</span><span class="v">₹{{ number_format($claimsMonth) }}</span></div>
</section>
@if($missing && auth()->user()->isAdmin())<p class="flash warn">{{ __(':n people have no employee code or joining date yet.', ['n' => $missing]) }} <a href="{{ route('hr.employees') }}">{{ __('Fill in') }}</a></p>@endif
<div class="grid g2 stretch">
  <section class="card flush"><div class="ch"><h2>{{ __('On leave today') }}</h2></div><div class="list">
    @forelse($onLeave as $l)<div class="item"><span class="bar warn"></span><div><b>{{ $l->user->name }}</b><small>{{ $l->type->name }} · {{ $l->from_date->format('d M') }} – {{ $l->to_date->format('d M') }}</small></div><span></span></div>
    @empty<p class="empty">{{ __('Nobody is on leave today.') }}</p>@endforelse
  </div></section>
  <section class="card flush"><div class="ch"><h2>{{ __('Coming holidays') }}</h2></div><div class="list">
    @forelse($holidays as $h)<div class="item"><span class="bar good"></span><div><b>{{ $h->name }}</b><small>{{ $h->date->translatedFormat('D, d M Y') }}</small></div><span></span></div>
    @empty<p class="empty">{{ __('No holidays added yet.') }}</p>@endforelse
  </div></section>
  <section class="card flush"><div class="ch"><h2>{{ __('Birthdays this month') }}</h2></div><div class="list">
    @forelse($birthdays as $u)<div class="item"><span class="bar {{ $u->profile->date_of_birth->day === now()->day ? 'good' : '' }}"></span><div><b>{{ $u->name }}</b><small>{{ $u->profile->date_of_birth->format('d M') }}</small></div><span></span></div>
    @empty<p class="empty">{{ __('None this month.') }}</p>@endforelse
  </div></section>
  <section class="card flush"><div class="ch"><h2>{{ __('Work anniversaries this month') }}</h2></div><div class="list">
    @forelse($anniversaries as $u)<div class="item"><span class="bar good"></span><div><b>{{ $u->name }}</b><small>{{ $u->profile->date_of_joining->format('d M') }} · {{ __(':n years', ['n' => now()->year - $u->profile->date_of_joining->year]) }}</small></div><span></span></div>
    @empty<p class="empty">{{ __('None this month.') }}</p>@endforelse
  </div></section>
</div>
@if($notice->isNotEmpty())<section class="card"><h2>{{ __('On notice') }}</h2><p>@foreach($notice as $u){{ $u->name }}@if($u->profile->exit_date) ({{ __('last day') }} {{ $u->profile->exit_date->format('d M') }})@endif{{ $loop->last ? '' : ', ' }}@endforeach</p></section>@endif
@endsection
