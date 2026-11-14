<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $q->number }}</title>
<style>
  @page { margin: 28px 32px 60px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #14201a; }
  .hd { border-bottom: 2px solid #14201a; padding-bottom: 10px; margin-bottom: 14px; }
  .hd td { vertical-align: top; }
  h1 { font-size: 17px; margin: 0 0 3px; }
  .muted { color: #5d6d65; }
  table { width: 100%; border-collapse: collapse; }
  .items th { background: #eef3f0; text-align: left; font-size: 9.5px; padding: 6px; border-bottom: 1px solid #c9d4cf; }
  .items td { padding: 6px; border-bottom: 1px solid #e3e9e5; vertical-align: top; }
  .r { text-align: right; } .tot td { font-weight: bold; font-size: 11.5px; }
  .terms { margin-top: 16px; white-space: pre-line; }
  .ft { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #7e9187; }
  .logo { height: 46px; }
</style></head>
<body>
<table class="hd"><tr>
  <td>@if($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif<h1>{{ $c->company_name }}</h1>
    <div class="muted">{{ $c->address }}</div>
    <div class="muted">@if($c->gstin)GSTIN: {{ $c->gstin }} @endif @if($c->drug_licence_no) · DL: {{ $c->drug_licence_no }}@endif</div>
    <div class="muted">@if($c->phone){{ $c->phone }}@endif @if($c->email) · {{ $c->email }}@endif</div></td>
  <td class="r"><h1>QUOTATION</h1><div><b>{{ $q->number }}</b></div><div>{{ $q->quote_date->format('d M Y') }}</div><div class="muted">Valid for {{ $q->valid_days }} days</div></td>
</tr></table>
<p><span class="muted">To</span><br><b>{{ $q->lead->firm_name }}</b>@if($q->lead->contact_name)<br>{{ $q->lead->contact_name }}@endif @if($q->lead->city)<br>{{ $q->lead->city }}{{ $q->lead->state ? ', '.$q->lead->state : '' }}@endif @if($q->lead->gstin)<br>GSTIN: {{ $q->lead->gstin }}@endif</p>
<table class="items">
  <thead><tr><th>#</th><th>Product</th><th>HSN</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Disc %</th><th class="r">GST %</th><th class="r">Amount</th></tr></thead>
  <tbody>
  @foreach($q->items as $i => $it)
    <tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}@if($it->packing)<br><span class="muted">{{ $it->packing }}</span>@endif</td><td>{{ $it->hsn }}</td>
      <td class="r">{{ number_format($it->qty) }}</td><td class="r">{{ number_format((float) $it->rate, 2) }}</td><td class="r">{{ (float) $it->discount_percent }}</td><td class="r">{{ (float) $it->gst_percent }}</td><td class="r">{{ number_format((float) $it->amount, 2) }}</td></tr>
  @endforeach
  <tr><td colspan="7" class="r">Subtotal</td><td class="r">{{ number_format((float) $q->subtotal, 2) }}</td></tr>
  <tr><td colspan="7" class="r">GST</td><td class="r">{{ number_format((float) $q->gst_total, 2) }}</td></tr>
  <tr><td colspan="7" class="r">Freight</td><td class="r">{{ number_format((float) $q->freight, 2) }}</td></tr>
  <tr class="tot"><td colspan="7" class="r">Total (₹)</td><td class="r">{{ number_format((float) $q->grand_total, 2) }}</td></tr>
  </tbody>
</table>
@if($q->terms)<div class="terms"><b>Terms</b><br>{{ $q->terms }}</div>@endif
<p class="muted">Prepared by {{ $q->user?->name }}</p>
<div class="ft">{{ $c->company_name }} · Generated with Pharma Sales CRM by Solvix Web</div>
</body></html>
