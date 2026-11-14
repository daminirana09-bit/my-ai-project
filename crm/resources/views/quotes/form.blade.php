@extends('layouts.app')
@section('title', __('New quote'))
@section('content')
<div class="head"><div><h1>{{ __('New quote') }}</h1><p>{{ __('Pick products; the rate, packing and GST fill in from the product list and can be changed. Empty lines are ignored.') }}</p></div></div>
<form method="post" action="{{ route('quotes.store') }}" class="form">
  @csrf
  <section class="card form">
    <div class="f3">
      <div class="field"><label for="lead_id">{{ __('Client') }}</label><select id="lead_id" name="lead_id" required><option value="">—</option>@foreach($leads as $l)<option value="{{ $l->id }}" @selected((int) old('lead_id', $lead?->id) === $l->id)>{{ $l->firm_name }}{{ $l->city ? ', '.$l->city : '' }}</option>@endforeach</select></div>
      <div class="field"><label for="valid_days">{{ __('Valid for (days)') }}</label><input type="number" min="1" max="180" id="valid_days" name="valid_days" value="{{ old('valid_days', 15) }}" required></div>
      <div class="field"><label for="freight">{{ __('Freight (₹)') }}</label><input type="number" min="0" step="0.01" id="freight" name="freight" value="{{ old('freight', 0) }}"></div>
    </div>
  </section>
  <section class="card flush">
    <div class="tbl"><table class="lines">
      <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Or description') }}</th><th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Rate (₹)') }}</th><th class="r">{{ __('Discount %') }}</th><th class="r">GST %</th></tr></thead>
      <tbody>
      @for($i = 0; $i < \App\Http\Controllers\QuoteController::LINES; $i++)
        <tr>
          <td><label class="sr" for="p{{ $i }}">{{ __('Product') }}</label><select id="p{{ $i }}" name="items[{{ $i }}][product_id]" data-line="{{ $i }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" data-rate="{{ $p->rate }}" data-gst="{{ $p->gst_percent }}" @selected((int) old("items.$i.product_id") === $p->id)>{{ $p->name }}{{ $p->packing ? ' · '.$p->packing : '' }}</option>@endforeach</select></td>
          <td><label class="sr" for="d{{ $i }}">{{ __('Or description') }}</label><input id="d{{ $i }}" name="items[{{ $i }}][description]" value="{{ old("items.$i.description") }}" maxlength="255"></td>
          <td class="r"><label class="sr" for="q{{ $i }}">{{ __('Qty') }}</label><input id="q{{ $i }}" type="number" min="1" step="1" name="items[{{ $i }}][qty]" value="{{ old("items.$i.qty") }}"></td>
          <td class="r"><label class="sr" for="r{{ $i }}">{{ __('Rate (₹)') }}</label><input id="r{{ $i }}" type="number" min="0" step="0.01" name="items[{{ $i }}][rate]" value="{{ old("items.$i.rate") }}"></td>
          <td class="r"><label class="sr" for="x{{ $i }}">{{ __('Discount %') }}</label><input id="x{{ $i }}" type="number" min="0" max="100" step="0.5" name="items[{{ $i }}][discount_percent]" value="{{ old("items.$i.discount_percent", 0) }}"></td>
          <td class="r"><label class="sr" for="g{{ $i }}">GST %</label><input id="g{{ $i }}" type="number" min="0" max="28" step="0.01" name="items[{{ $i }}][gst_percent]" value="{{ old("items.$i.gst_percent") }}"></td>
        </tr>
      @endfor
      </tbody>
    </table></div>
  </section>
  <section class="card form">
    <div class="field"><label for="terms">{{ __('Terms') }}</label><textarea id="terms" name="terms" maxlength="2000">{{ old('terms', $terms) }}</textarea></div>
    <div class="row"><button class="btn p">{{ __('Create quote') }}</button><a class="btn" href="{{ route('quotes.index') }}">{{ __('Cancel') }}</a></div>
  </section>
</form>
@endsection
