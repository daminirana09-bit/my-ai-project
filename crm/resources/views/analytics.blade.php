@extends('layouts.app')
@section('title', __('Analytics'))
@section('content')
@php
  $S = \App\Models\Lead::STATUSES;
  $maxA = max(1, $trend->max('added')); $maxS = max(1, $trend->max('sales'));
  $W = 640; $H = 220; $L = 36; $B = 26; $T = 14; $iw = $W - $L - 8; $ih = $H - $B - $T; $bw = $iw / max(1, $trend->count());
  $totalLeads = $statusCounts->sum();
@endphp
<div class="head"><div><h1>{{ __('Analytics') }}</h1><p>{{ auth()->user()->isRep() ? __('Your own numbers.') : __('Your team\'s numbers.') }}</p></div></div>

<div class="grid g2 stretch">
  <section class="card chart">
    <div class="ch"><h2>{{ __('New leads per month') }}</h2></div>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ __('New leads per month') }}">
      @foreach([0, .5, 1] as $f)@php $y = $T + $ih - $ih * $f; @endphp<line class="grid-l" x1="{{ $L }}" x2="{{ $W - 8 }}" y1="{{ $y }}" y2="{{ $y }}"/><text class="tick" x="{{ $L - 6 }}" y="{{ $y + 4 }}" text-anchor="end">{{ round($maxA * $f) }}</text>@endforeach
      @foreach($trend as $i => $m)@php $h = $ih * $m->added / $maxA; $x = $L + $i * $bw + $bw * .2; @endphp
        <rect class="b1" x="{{ $x }}" y="{{ $T + $ih - $h }}" width="{{ $bw * .6 }}" height="{{ $h }}" rx="3"/>
        <text class="vl" x="{{ $x + $bw * .3 }}" y="{{ $T + $ih - $h - 5 }}" text-anchor="middle">{{ $m->added }}</text>
        <text class="tick" x="{{ $x + $bw * .3 }}" y="{{ $H - 8 }}" text-anchor="middle">{{ $m->label }}</text>
      @endforeach
    </svg>
  </section>
  <section class="card chart">
    <div class="ch"><h2>{{ __('Confirmed sales per month') }}</h2></div>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ __('Confirmed sales per month') }}">
      @foreach([0, .5, 1] as $f)@php $y = $T + $ih - $ih * $f; @endphp<line class="grid-l" x1="{{ $L }}" x2="{{ $W - 8 }}" y1="{{ $y }}" y2="{{ $y }}"/>@endforeach
      @foreach($trend as $i => $m)@php $h = $ih * $m->sales / $maxS; $x = $L + $i * $bw + $bw * .2; @endphp
        <rect class="b2" x="{{ $x }}" y="{{ $T + $ih - $h }}" width="{{ $bw * .6 }}" height="{{ $h }}" rx="3"/>
        <text class="vl" x="{{ $x + $bw * .3 }}" y="{{ $T + $ih - $h - 5 }}" text-anchor="middle">{{ $m->sales ? inr($m->sales) : '' }}</text>
        <text class="tick" x="{{ $x + $bw * .3 }}" y="{{ $H - 8 }}" text-anchor="middle">{{ $m->label }}</text>
      @endforeach
    </svg>
  </section>
</div>

<div class="grid g2 stretch">
  <section class="card flush">
    <div class="ch"><h2>{{ __('Lead source: which channel works') }}</h2></div>
    <div class="tbl"><table>
      <thead><tr><th>{{ __('Source') }}</th><th class="r">{{ __('Leads') }}</th><th class="r">{{ __('Won') }}</th><th>{{ __('Conversion') }}</th></tr></thead>
      <tbody>
      @forelse($bySource as $s)
        <tr><td>{{ $s->src }}</td><td class="r num">{{ $s->total }}</td><td class="r num">{{ $s->won }}</td>
          <td><meter min="0" max="100" value="{{ $s->total ? round($s->won / $s->total * 100) : 0 }}"></meter><span class="sub">{{ $s->total ? round($s->won / $s->total * 100) : 0 }}%</span></td></tr>
      @empty
        <tr><td colspan="4" class="empty">{{ __('No leads yet.') }}</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
  <section class="card">
    <h2>{{ __('Leads by status') }}</h2>
    <div class="bars">
      @foreach($S as $k => $label)
        @php $n = (int) ($statusCounts[$k] ?? 0); @endphp
        <div class="hbar"><a href="{{ route('leads.index', ['status' => $k]) }}">{{ __($label) }}</a><meter min="0" max="{{ max($totalLeads, 1) }}" value="{{ $n }}"></meter><span class="r num">{{ $n }}</span></div>
      @endforeach
    </div>
    <h3 class="sub-h">{{ __('Open leads by temperature') }}</h3>
    <div class="tiles">
      @foreach(['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'] as $k => $l)<a class="tile {{ $k }}" href="{{ route('leads.index', ['temperature' => $k]) }}"><span class="l">{{ __($l) }}</span><span class="v">{{ (int) ($tempCounts[$k] ?? 0) }}</span></a>@endforeach
    </div>
  </section>
</div>

@if(! auth()->user()->isRep())
<section class="card flush">
  <div class="ch"><h2>{{ __('Salesperson-wise conversion') }}</h2></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Leads') }}</th><th class="r">{{ __('Won') }}</th><th class="r">{{ __('Lost') }}</th><th>{{ __('Conversion') }}</th></tr></thead>
    <tbody>
    @foreach($people as $p)
      @php $r = $perUser->get($p->id); $tot = (int) ($r->total ?? 0); $won = (int) ($r->won ?? 0); @endphp
      <tr><td>{{ $p->name }}</td><td class="r num">{{ $tot }}</td><td class="r num">{{ $won }}</td><td class="r num">{{ (int) ($r->lost ?? 0) }}</td>
        <td><meter min="0" max="100" value="{{ $tot ? round($won / $tot * 100) : 0 }}"></meter><span class="sub">{{ $tot ? round($won / $tot * 100) : 0 }}%</span></td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endif
@endsection
