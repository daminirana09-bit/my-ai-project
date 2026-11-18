<!doctype html>
<html><head><meta charset="utf-8"><title>{{ __('Monthly sales report') }}</title>
<style>
  @page { margin: 24px 28px 54px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #14201a; }
  .hd { border-bottom: 2px solid #14201a; padding-bottom: 8px; margin-bottom: 10px; width: 100%; }
  .hd td { vertical-align: top; }
  h1 { font-size: 15px; margin: 0 0 3px; }
  .muted { color: #5d6d65; }
  table.t { width: 100%; border-collapse: collapse; }
  .t th { background: #eef3f0; text-align: left; font-size: 8px; padding: 4px; border-bottom: 1px solid #c9d4cf; }
  .t td { padding: 4px; border-bottom: 1px solid #e3e9e5; }
  .t tfoot td { font-weight: bold; border-top: 1px solid #14201a; }
  .r { text-align: right; }
  .ft { position: fixed; bottom: -36px; left: 0; right: 0; text-align: center; font-size: 8px; color: #7e9187; }
  .logo { height: 40px; }
</style></head>
<body>
@php $tt = $rows->sum('target'); $ta = $rows->sum('achieved'); @endphp
<table class="hd"><tr>
  <td>@if($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif<h1>{{ $c->company_name }}</h1><div class="muted">{{ $c->address }}</div></td>
  <td class="r"><h1>{{ mb_strtoupper(__('Monthly sales report')) }}</h1><div>{{ $month->format('F Y') }}</div><div class="muted">{{ now()->format('d M Y, h:i A') }}</div></td>
</tr></table>
<table class="t">
  <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Target') }}</th><th class="r">{{ __('Achieved') }}</th><th class="r">%</th><th class="r">{{ __('Orders') }}</th><th class="r">{{ __('New / repeat customers') }}</th><th class="r">{{ __('Last month') }}</th><th class="r">{{ __('Growth') }}</th><th class="r">{{ __('Leads added / won / lost') }}</th><th class="r">{{ __('Calls / visits') }}</th><th class="r">{{ __('Days present') }}</th></tr></thead>
  <tbody>
  @foreach($rows as $r)
    <tr><td>{{ $r->user->name }}</td><td class="r">{{ inr($r->target, false) }}</td><td class="r">{{ inr($r->achieved, false) }}</td><td class="r">{{ $r->percent === null ? '—' : $r->percent.'%' }}</td>
      <td class="r">{{ $r->orders }}</td><td class="r">{{ $r->new_customers }} / {{ $r->repeat_customers }}</td><td class="r">{{ inr($r->last_month, false) }}</td>
      <td class="r">{{ $r->growth === null ? '—' : ($r->growth > 0 ? '+' : '').$r->growth.'%' }}</td><td class="r">{{ $r->leads_added }} / {{ $r->won }} / {{ $r->lost }}</td><td class="r">{{ $r->calls }} / {{ $r->visits }}</td><td class="r">{{ rtrim(rtrim(number_format($r->present, 1), '0'), '.') }}</td></tr>
  @endforeach
  </tbody>
  <tfoot><tr><td>{{ __('Total') }}</td><td class="r">{{ inr($tt, false) }}</td><td class="r">{{ inr($ta, false) }}</td><td class="r">{{ $tt > 0 ? round($ta * 100 / $tt).'%' : '' }}</td><td class="r">{{ $rows->sum('orders') }}</td>
    <td class="r">{{ $rows->sum('new_customers') }} / {{ $rows->sum('repeat_customers') }}</td><td class="r">{{ inr($rows->sum('last_month'), false) }}</td><td></td><td class="r">{{ $rows->sum('leads_added') }} / {{ $rows->sum('won') }} / {{ $rows->sum('lost') }}</td><td class="r">{{ $rows->sum('calls') }} / {{ $rows->sum('visits') }}</td><td></td></tr></tfoot>
</table>
<div class="ft">{{ $c->company_name }} · {{ __('Designed & developed by Solvix Web · Damini Rana · +91 82190 60177') }}</div>
</body></html>
