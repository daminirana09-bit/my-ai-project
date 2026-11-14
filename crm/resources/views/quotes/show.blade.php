@extends('layouts.app')
@section('title', $quote->number)
@section('content')
<div class="head">
  <div><h1>{{ $quote->number }}</h1><p>{{ $quote->lead->firm_name }} · {{ $quote->quote_date->format('d M Y') }} · {{ __('valid :n days', ['n' => $quote->valid_days]) }}</p></div>
  <div class="row">
    <a class="btn" href="{{ route('quotes.pdf', $quote) }}" target="_blank" rel="noopener"><x-icon name="download"/>{{ __('Open PDF') }}</a>
    <form method="post" action="{{ route('quotes.send', $quote) }}">@csrf<button class="btn p">{{ __('Send on WhatsApp') }}</button></form>
  </div>
</div>
<section class="card flush"><div class="tbl"><table>
  <thead><tr><th>#</th><th>{{ __('Product') }}</th><th>HSN</th><th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Discount %') }}</th><th class="r">GST %</th><th class="r">{{ __('Amount') }}</th></tr></thead>
  <tbody>
  @foreach($quote->items as $i => $it)
    <tr><td>{{ $i + 1 }}</td><td><b>{{ $it->description }}</b>@if($it->packing)<span class="sub">{{ $it->packing }}</span>@endif</td><td class="mono">{{ $it->hsn ?: '—' }}</td>
      <td class="r num">{{ number_format($it->qty) }}</td><td class="r num">{{ number_format((float) $it->rate, 2) }}</td><td class="r num">{{ (float) $it->discount_percent }}</td><td class="r num">{{ (float) $it->gst_percent }}</td><td class="r num">{{ inr($it->amount, false) }}</td></tr>
  @endforeach
  <tr><td colspan="7" class="r">{{ __('Subtotal') }}</td><td class="r num">{{ inr($quote->subtotal, false) }}</td></tr>
  <tr><td colspan="7" class="r">GST</td><td class="r num">{{ inr($quote->gst_total, false) }}</td></tr>
  <tr><td colspan="7" class="r">{{ __('Freight') }}</td><td class="r num">{{ inr($quote->freight, false) }}</td></tr>
  <tr><td colspan="7" class="r"><b>{{ __('Total') }}</b></td><td class="r num"><b>{{ inr($quote->grand_total, false) }}</b></td></tr>
  </tbody>
</table></div></section>
<div class="grid g2 stretch">
  <section class="card"><h2>{{ __('Terms') }}</h2><p class="pre">{{ $quote->terms }}</p></section>
  <form method="post" action="{{ route('quotes.status', $quote) }}" class="card form">@csrf @method('patch')
    <h2>{{ __('Status') }}</h2>
    <div class="field"><label for="qs">{{ __('Status') }}</label><select id="qs" name="status" data-autosubmit>@foreach(\App\Models\Quote::STATUSES as $k => $l)<option value="{{ $k }}" @selected($quote->status === $k)>{{ __($l) }}</option>@endforeach</select></div>
  </form>
</div>
@endsection
