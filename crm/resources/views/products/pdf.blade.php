<!doctype html>
<html><head><meta charset="utf-8"><title>{{ __('Price list') }}</title>
<style>
  @page { margin: 28px 32px 60px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #14201a; }
  .hd { border-bottom: 2px solid #14201a; padding-bottom: 10px; margin-bottom: 12px; }
  .hd td { vertical-align: top; }
  h1 { font-size: 17px; margin: 0 0 3px; }
  h2 { font-size: 11.5px; margin: 14px 0 4px; padding: 4px 6px; background: #e3f5e9; color: #15803d; }
  .muted { color: #5d6d65; }
  table { width: 100%; border-collapse: collapse; }
  .items th { background: #eef3f0; text-align: left; font-size: 9px; padding: 5px; border-bottom: 1px solid #c9d4cf; }
  .items td { padding: 5px; border-bottom: 1px solid #e3e9e5; vertical-align: top; }
  .r { text-align: right; }
  .ft { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #7e9187; }
  .logo { height: 46px; }
</style></head>
<body>
<table class="hd"><tr>
  <td>@if($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif<h1>{{ $c->company_name }}</h1>
    <div class="muted">{{ $c->address }}</div>
    <div class="muted">@if($c->gstin)GSTIN: {{ $c->gstin }} @endif @if($c->drug_licence_no) · DL: {{ $c->drug_licence_no }}@endif</div>
    <div class="muted">{{ implode(' · ', array_filter([$c->phone, $c->email, $c->link('website')])) }}</div></td>
  <td class="r"><h1>{{ mb_strtoupper(__('Price list')) }}</h1><div>{{ now()->format('d M Y') }}</div></td>
</tr></table>
@forelse($groups as $category => $list)
  <h2>{{ $category }}</h2>
  <table class="items">
    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Composition') }}</th><th>{{ __('Packing') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">MOQ</th><th class="r">GST</th></tr></thead>
    <tbody>
    @foreach($list as $p)
      <tr><td><b>{{ $p->name }}</b>@if($p->form)<br><span class="muted">{{ $p->form }}</span>@endif</td><td>{{ $p->composition }}</td><td>{{ $p->packing }}</td>
        <td class="r">{{ $p->rate !== null ? 'Rs. '.number_format((float) $p->rate, 2).' / '.__($p->unit) : '—' }}</td>
        <td class="r">{{ $p->moq ? number_format($p->moq) : '—' }}</td><td class="r">{{ rtrim(rtrim(number_format((float) $p->gst_percent, 2), '0'), '.') }}%</td></tr>
    @endforeach
    </tbody>
  </table>
@empty
  <p class="muted">{{ __('No products yet.') }}</p>
@endforelse
<div class="ft">{{ __('Rates are subject to change. GST extra as shown.') }} · {{ $c->company_name }}</div>
</body></html>
