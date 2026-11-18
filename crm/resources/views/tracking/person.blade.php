@extends('layouts.app')
@section('title', $person->name.' · '.$day->format('d M'))
@section('content')
@php $D = fn ($m) => \App\Http\Controllers\TrackingController::distanceLabel($m); $far = \App\Http\Controllers\TrackingController::FAR_METRES; @endphp
<div class="head"><div><h1>{{ $person->name }}</h1><p>{{ $day->translatedFormat('l, d M Y') }}</p></div>
  <div class="row">
    <a class="btn" href="{{ route('tracking.person', ['user' => $person, 'date' => $day->subDay()->toDateString()]) }}">← {{ __('Previous day') }}</a>
    @if($day->lt(today()))<a class="btn" href="{{ route('tracking.person', ['user' => $person, 'date' => $day->addDay()->toDateString()]) }}">{{ __('Next day') }} →</a>@endif
    @if(auth()->user()->canManage())<a class="btn" href="{{ route('tracking.team', ['date' => $day->toDateString()]) }}">{{ __('Whole team') }}</a>@endif
  </div></div>
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('In / out') }}</span><span class="v">{{ $s['in']?->punched_at->format('h:i') ?? '—' }} – {{ $s['out']?->punched_at->format('h:i') ?? '—' }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Calls') }}</span><span class="v">{{ $s['calls'] }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Visits') }}</span><span class="v">{{ $s['visits'] }}</span>@if($s['far'])<span class="small bad-t">{{ __(':n far from client', ['n' => $s['far']]) }}</span>@endif</div>
  <div class="card kpi"><span class="l">WhatsApp</span><span class="v">{{ $s['whatsapp'] }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Orders') }}</span><span class="v">{{ $s['orders'] ? inr($s['orders']) : '—' }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Km (straight line)') }}</span><span class="v">{{ $km ?: '—' }}</span>@if($route)<a class="small" href="{{ $route }}" target="_blank" rel="noopener noreferrer">{{ __('See the route on Google Maps') }}</a>@endif</div>
</section>
<section class="card">
  <h2>{{ __('The day, step by step') }}</h2>
  <div class="tl-list">
    @forelse($events as $e)
      <div class="ev"><time>{{ $e->at->format('h:i A') }}</time>
        <div><b>{{ $e->title }}@if($e->lead) · <a href="{{ route('leads.show', $e->lead->id) }}">{{ $e->lead->firm_name }}</a>@if($e->lead->city) <span class="muted">({{ $e->lead->city }})</span>@endif @endif</b>
          @if($e->detail)<span class="small">{{ $e->detail }}</span>@endif
          @if($e->lat !== null || $e->distance !== null)<span class="row">
            @if($e->lat !== null)<a class="chip" href="{{ \App\Support\Geo::mapLink((float) $e->lat, (float) $e->lng) }}" target="_blank" rel="noopener noreferrer"><x-icon name="pin"/>{{ __('Map') }}@if($e->accuracy) (±{{ $e->accuracy }} m)@endif</a>@endif
            @if($e->distance !== null)<span class="pill {{ $e->distance > $far ? 'bad' : 'good' }}">{{ __(':d from client', ['d' => $D($e->distance)]) }}</span>@endif
          </span>@endif</div></div>
    @empty
      <p class="empty">{{ __('Nothing recorded on this day.') }}</p>
    @endforelse
  </div>
</section>
@endsection
