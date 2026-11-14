@extends('layouts.app')
@section('title', __('Customers'))
@section('content')
<div class="head"><div><h1>{{ __('Customers') }}</h1><p>{{ __('Clients whose lead is won, with their confirmed orders. A customer with no order for 30 days is flagged so you can follow up for a reorder.') }}</p></div></div>
<form method="get" class="card filters">
  <div class="field"><label for="q">{{ __('Search firm, contact or city') }}</label><input id="q" name="q" value="{{ request('q') }}"></div>
  <button class="btn"><x-icon name="search"/>{{ __('Search') }}</button>
</form>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Contact') }}</th><th class="r">{{ __('Orders') }}</th><th class="r">{{ __('Business') }}</th><th>{{ __('Last order') }}</th><th>{{ __('Reorder') }}</th>@if(auth()->user()->canManage())<th>{{ __('Salesperson') }}</th>@endif</tr></thead>
    <tbody>
    @forelse($customers as $c)
      @php $s = $stats->get($c->id); $last = $s?->last_order ? \Illuminate\Support\Carbon::parse($s->last_order) : null; $days = $last ? (int) $last->diffInDays(today()) : null; @endphp
      <tr>
        <td><a href="{{ route('leads.show', $c) }}"><b>{{ $c->firm_name }}</b></a><span class="sub">{{ $c->city }}{{ $c->client_type ? ' · '.$c->client_type : '' }}</span></td>
        <td>{{ $c->contact_name ?: '—' }}<span class="sub mono">{{ $c->maskedPhone() }}</span></td>
        <td class="r num">{{ $s->orders ?? 0 }}</td>
        <td class="r num">{{ inr($s->total ?? 0) }}</td>
        <td class="nowrap">{{ $last ? $last->format('d M Y') : '—' }}</td>
        <td>@if($days === null)<span class="pill">{{ __('No order yet') }}</span>@elseif($days > 30)<span class="pill bad">{{ __(':n days ago', ['n' => $days]) }}</span>@else<span class="pill good">{{ __('Recent') }}</span>@endif</td>
        @if(auth()->user()->canManage())<td>{{ $c->owner?->name ?? __('Unassigned') }}</td>@endif
      </tr>
    @empty
      <tr><td colspan="7" class="empty">{{ __('No customers yet. A lead becomes a customer when its status is Won.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@include('partials.pager', ['p' => $customers])
@endsection
