@extends('layouts.app')
@section('title', __('Deals report'))
@section('content')
@php $S = \App\Models\Lead::STATUSES; $C = \App\Services\Reports::CHANCE; $maxStage = max(1, $r['stages']->max('value')); @endphp
<div class="head"><div><h1>{{ __('Deals report') }}</h1><p>{{ __('Open pipeline today, and deals won and lost between the dates. Deal value is the expected value entered on each lead.') }}</p></div>
  <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><x-icon name="download"/>{{ __('Excel') }}</a></div>
<form method="get" class="card form row filters">
  <div class="field"><label for="from">{{ __('From') }}</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}"></div>
  <div class="field"><label for="to">{{ __('To') }}</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}"></div>
  @if($owners->isNotEmpty())<div class="field"><label for="owner_id">{{ __('Salesperson') }}</label><select id="owner_id" name="owner_id"><option value="">{{ __('All') }}</option>@foreach($owners as $o)<option value="{{ $o->id }}" @selected((int) request('owner_id') === $o->id)>{{ $o->name }}</option>@endforeach</select></div>@endif
  <div class="field"><label for="source">{{ __('Source') }}</label><select id="source" name="source"><option value="">{{ __('All') }}</option>@foreach($sources as $s)<option @selected(request('source') === $s)>{{ $s }}</option>@endforeach</select></div>
  <button class="btn p">{{ __('Show') }}</button>
</form>
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('Open deals') }}</span><span class="v">{{ $r['open_count'] }}</span><span class="small muted">{{ inr($r['open_value']) }} · {{ __('weighted') }} {{ inr($r['weighted']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Won') }}</span><span class="v">{{ $r['won_count'] }}</span><span class="small muted">{{ inr($r['won_value']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Lost') }}</span><span class="v">{{ $r['lost_count'] }}</span><span class="small muted">{{ inr($r['lost_value']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Win rate') }}</span><span class="v">{{ $r['rate'] === null ? '—' : $r['rate'].'%' }}</span><span class="small muted">{{ __(':n new deals', ['n' => $r['created']]) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Average deal') }}</span><span class="v">{{ $r['avg_deal'] === null ? '—' : inr($r['avg_deal']) }}</span><span class="small muted">{{ $r['avg_days'] === null ? '' : __(':d days to win', ['d' => $r['avg_days']]) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Orders received') }}</span><span class="v">{{ inr($r['orders']) }}</span></div>
</section>
<div class="grid g2 stretch">
  <section class="card"><h2>{{ __('Pipeline by stage') }}</h2>
    <div class="funnel">
      @foreach($r['stages'] as $st => $x)
        <div class="frow"><span class="l">{{ __($S[$st]) }} <span class="muted small">({{ $x->count }} · {{ $C[$st] }}%)</span></span><meter min="0" max="100" value="{{ round($x->value * 100 / $maxStage) }}"></meter><b class="num">{{ inr($x->value) }}</b></div>
      @endforeach
    </div>
    <p class="small muted">{{ __('Weighted value = deal value × chance of winning at that stage.') }}</p>
  </section>
  <section class="card"><h2>{{ __('Why deals were lost') }}</h2>
    <div class="funnel">
      @forelse($r['reasons'] as $why => $n)
        <div class="frow"><span class="l">{{ $why }}</span><meter min="0" max="{{ max(1, $r['lost_count']) }}" value="{{ $n }}"></meter><b class="num">{{ $n }}</b></div>
      @empty<p class="empty">{{ __('No deal lost in these dates.') }}</p>@endforelse
    </div>
  </section>
</div>
<section class="card flush"><div class="ch"><h2>{{ __('By salesperson') }}</h2></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Open') }}</th><th class="r">{{ __('Open value') }}</th><th class="r">{{ __('Won') }}</th><th class="r">{{ __('Won value') }}</th><th class="r">{{ __('Lost') }}</th><th>{{ __('Win rate') }}</th><th class="r">{{ __('Days to win') }}</th></tr></thead>
    <tbody>@forelse($r['people'] as $p)
      <tr><td><b>{{ $p->user->name }}</b></td><td class="r num">{{ $p->open }}</td><td class="r num">{{ inr($p->open_value) }}</td><td class="r num">{{ $p->won }}</td><td class="r num">{{ inr($p->won_value) }}</td><td class="r num">{{ $p->lost }}</td>
        <td>@if($p->rate !== null)<meter min="0" max="100" value="{{ $p->rate }}"></meter><span class="sub">{{ $p->rate }}%</span>@else — @endif</td><td class="r num">{{ $p->days ?? '—' }}</td></tr>
    @empty<tr><td colspan="8" class="empty">{{ __('No deals.') }}</td></tr>@endforelse</tbody>
  </table></div>
</section>
<div class="grid g2 stretch">
  <section class="card flush"><div class="ch"><h2>{{ __('Deals by source (created in these dates)') }}</h2></div>
    <div class="tbl"><table><thead><tr><th>{{ __('Source') }}</th><th class="r">{{ __('Deals') }}</th><th class="r">{{ __('Won') }}</th><th class="r">%</th><th class="r">{{ __('Won value') }}</th></tr></thead>
      <tbody>@forelse($r['sources'] as $s)<tr><td>{{ $s->source }}</td><td class="r num">{{ $s->leads }}</td><td class="r num">{{ $s->won }}</td><td class="r num">{{ $s->rate }}%</td><td class="r num">{{ inr($s->value) }}</td></tr>
      @empty<tr><td colspan="5" class="empty">{{ __('No deals.') }}</td></tr>@endforelse</tbody></table></div>
  </section>
  <section class="card flush"><div class="ch"><h2>{{ __('Open deals not touched for 30+ days') }}</h2></div>
    <div class="list">@forelse($r['stale'] as $l)
      <a class="item" href="{{ route('leads.show', $l->id) }}"><span class="bar bad"></span><div><b>{{ $l->firm_name }}</b><small>{{ __($S[$l->status]) }} · {{ $l->city }} · {{ __('last contact :d', ['d' => ($l->last_contact_at ?? $l->created_at)->diffForHumans()]) }}</small></div><span class="num">{{ $l->expected_value ? inr($l->expected_value) : '' }}</span></a>
    @empty<p class="empty">{{ __('Every open deal was touched in the last 30 days.') }}</p>@endforelse</div>
  </section>
</div>
@endsection
