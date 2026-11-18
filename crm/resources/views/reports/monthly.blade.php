@extends('layouts.app')
@section('title', __('Monthly sales report'))
@section('content')
@php $t = (object) ['target' => $rows->sum('target'), 'achieved' => $rows->sum('achieved'), 'last' => $rows->sum('last_month')]; @endphp
<div class="head"><div><h1>{{ __('Monthly sales report') }}</h1><p>{{ __('Every salesperson for :m: target, orders, new and repeat customers, growth over last month, and the work behind it.', ['m' => $month->translatedFormat('F Y')]) }}</p></div>
  <div class="row"><form method="get" class="row"><label class="sr" for="month">{{ __('Month') }}</label><input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" data-autosubmit></form>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><x-icon name="download"/>{{ __('Excel') }}</a>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank" rel="noopener"><x-icon name="download"/>PDF</a></div></div>
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('Target') }}</span><span class="v">{{ inr($t->target) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Achieved') }}</span><span class="v">{{ inr($t->achieved) }}</span><span class="small muted">{{ $t->target > 0 ? round($t->achieved * 100 / $t->target).'%' : '' }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Orders') }}</span><span class="v">{{ $rows->sum('orders') }}</span></div>
  <div class="card kpi"><span class="l">{{ __('New customers') }}</span><span class="v">{{ $rows->sum('new_customers') }}</span><span class="small muted">{{ inr($rows->sum('new_value')) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Repeat customers') }}</span><span class="v">{{ $rows->sum('repeat_customers') }}</span><span class="small muted">{{ inr($rows->sum('repeat_value')) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Growth vs last month') }}</span><span class="v">{{ $t->last > 0 ? round(($t->achieved - $t->last) * 100 / $t->last).'%' : '—' }}</span><span class="small muted">{{ __('Last month') }} {{ inr($t->last) }}</span></div>
</section>
<section class="card flush">
  <div class="tbl"><table class="report">
    <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Target') }}</th><th class="r">{{ __('Achieved') }}</th><th>{{ __('Achieved %') }}</th><th class="r">{{ __('Orders') }}</th><th class="r">{{ __('New / repeat customers') }}</th><th class="r">{{ __('Last month') }}</th><th class="r">{{ __('Growth') }}</th><th class="r">{{ __('Leads added / won / lost') }}</th><th class="r">{{ __('Calls / visits') }}</th><th class="r">{{ __('Days present') }}</th></tr></thead>
    <tbody>
    @foreach($rows as $r)
      <tr><td><b>{{ $r->user->name }}</b>@if($r->pending)<span class="sub">{{ __('Pending orders') }} {{ inr($r->pending) }}</span>@endif</td>
        <td class="r num">{{ $r->target ? inr($r->target) : '—' }}</td><td class="r num"><b>{{ inr($r->achieved) }}</b>@if($r->gap)<span class="sub">{{ __('gap') }} {{ inr($r->gap) }}</span>@endif</td>
        <td>@if($r->percent !== null)<meter min="0" max="100" low="60" high="90" optimum="100" value="{{ min(100, $r->percent) }}"></meter><span class="sub">{{ $r->percent }}%</span>@else — @endif</td>
        <td class="r num">{{ $r->orders }}@if($r->orders)<span class="sub">{{ __('avg') }} {{ inr($r->avg_order) }}</span>@endif</td>
        <td class="r num">{{ $r->new_customers }} / {{ $r->repeat_customers }}</td><td class="r num">{{ inr($r->last_month) }}</td>
        <td class="r num">@if($r->growth === null)—@else<span class="{{ $r->growth < 0 ? 'bad-t' : 'good-t' }}">{{ $r->growth > 0 ? '+' : '' }}{{ $r->growth }}%</span>@endif</td>
        <td class="r num">{{ $r->leads_added }} / {{ $r->won }} / {{ $r->lost }}</td><td class="r num">{{ $r->calls }} / {{ $r->visits }}</td><td class="r num">{{ rtrim(rtrim(number_format($r->present, 1), '0'), '.') }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><th>{{ __('Total') }}</th><th class="r">{{ inr($t->target) }}</th><th class="r">{{ inr($t->achieved) }}</th><th>{{ $t->target > 0 ? round($t->achieved * 100 / $t->target).'%' : '' }}</th><th class="r">{{ $rows->sum('orders') }}</th>
      <th class="r">{{ $rows->sum('new_customers') }} / {{ $rows->sum('repeat_customers') }}</th><th class="r">{{ inr($t->last) }}</th><th></th><th class="r">{{ $rows->sum('leads_added') }} / {{ $rows->sum('won') }} / {{ $rows->sum('lost') }}</th><th class="r">{{ $rows->sum('calls') }} / {{ $rows->sum('visits') }}</th><th></th></tr></tfoot>
  </table></div>
</section>
@endsection
