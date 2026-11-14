@extends('layouts.app')
@section('title', __('Quotations'))
@section('content')
<div class="head"><div><h1>{{ __('Quotations') }}</h1><p>{{ __('Quotations with GST, as PDF, sent on WhatsApp in one click.') }}</p></div>
  <a class="btn p" href="{{ route('quotes.create') }}"><x-icon name="plus"/>{{ __('New quote') }}</a></div>
<section class="card flush"><div class="tbl"><table>
  <thead><tr><th>{{ __('Quote') }}</th><th>{{ __('Client') }}</th><th>{{ __('Date') }}</th><th class="r">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
  <tbody>
  @forelse($quotes as $q)
    <tr><td><a href="{{ route('quotes.show', $q) }}" class="mono"><b>{{ $q->number }}</b></a><span class="sub">{{ $q->user?->name }}</span></td>
      <td>{{ $q->lead?->firm_name }}<span class="sub">{{ $q->lead?->city }}</span></td><td class="nowrap">{{ $q->quote_date->format('d M Y') }}</td>
      <td class="r num">{{ inr($q->grand_total, false) }}</td><td><span class="pill {{ ['draft' => '', 'sent' => 'accent', 'accepted' => 'good', 'rejected' => 'bad'][$q->status] }}">{{ __(\App\Models\Quote::STATUSES[$q->status]) }}</span></td>
      <td class="r"><a class="btn sm" href="{{ route('quotes.pdf', $q) }}" target="_blank" rel="noopener"><x-icon name="download"/>PDF</a></td></tr>
  @empty
    <tr><td colspan="6" class="empty">{{ __('No quotations yet.') }}</td></tr>
  @endforelse
  </tbody>
</table></div></section>
@include('partials.pager', ['p' => $quotes])
@endsection
