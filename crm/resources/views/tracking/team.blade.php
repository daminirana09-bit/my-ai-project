@extends('layouts.app')
@section('title', __('Team tracking'))
@section('content')
@php $D = fn ($m) => \App\Http\Controllers\TrackingController::distanceLabel($m); @endphp
<div class="head"><div><h1>{{ __('Team tracking') }}</h1><p>{{ __('What each person did in the day: punch in/out, calls, visits checked in at the client, WhatsApp, new leads, quotations and orders. Places come only from punches and visit check-ins; nobody is followed in the background.') }}</p></div>
  <form method="get" class="row"><label class="sr" for="date">{{ __('Date') }}</label><input type="date" id="date" name="date" value="{{ $day->toDateString() }}" max="{{ today()->toDateString() }}" data-autosubmit></form></div>
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('Punched in') }}</span><span class="v">{{ $rows->filter(fn ($r) => $r->in)->count() }} / {{ $rows->count() }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Calls') }}</span><span class="v">{{ $rows->sum('calls') }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Visits') }}</span><span class="v">{{ $rows->sum('visits') }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Visits far from client') }}</span><span class="v">{{ $rows->sum('far') }}</span></div>
  <div class="card kpi"><span class="l">{{ __('New leads') }}</span><span class="v">{{ $rows->sum('leads') }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Orders') }}</span><span class="v">{{ inr($rows->sum('orders')) }}</span></div>
</section>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Person') }}</th><th>{{ __('In') }}</th><th>{{ __('Out') }}</th><th class="r">{{ __('Calls') }}</th><th class="r">{{ __('Visits') }}</th><th class="r">WhatsApp</th><th class="r">{{ __('New leads') }}</th><th class="r">{{ __('Quotes') }}</th><th class="r">{{ __('Orders') }}</th><th class="r">{{ __('Km (straight line)') }}</th><th>{{ __('Last activity') }}</th></tr></thead>
    <tbody>
    @foreach($rows as $r)
      <tr><td><a href="{{ route('tracking.person', ['user' => $r->user, 'date' => $day->toDateString()]) }}"><b>{{ $r->user->name }}</b></a></td>
        <td>@if($r->in){{ $r->in->punched_at->format('h:i A') }}@if($r->in->lat !== null) <a href="{{ \App\Support\Geo::mapLink((float) $r->in->lat, (float) $r->in->lng) }}" target="_blank" rel="noopener noreferrer" title="{{ __('Map') }}"><x-icon name="pin"/></a>@endif @else<span class="pill bad">{{ __('Not punched') }}</span>@endif</td>
        <td>{{ $r->out?->punched_at->format('h:i A') ?? '—' }}</td>
        <td class="r num">{{ $r->calls }}</td><td class="r num">{{ $r->visits }}@if($r->far)<span class="sub bad-t">{{ __(':n far', ['n' => $r->far]) }}</span>@endif</td>
        <td class="r num">{{ $r->whatsapp }}</td><td class="r num">{{ $r->leads }}</td><td class="r num">{{ $r->quotes }}</td><td class="r num">{{ $r->orders ? inr($r->orders) : '—' }}</td>
        <td class="r num">{{ $r->km ?: '—' }}</td><td class="small">{{ $r->last ? \Illuminate\Support\Carbon::parse($r->last)->format('h:i A') : '—' }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endsection
