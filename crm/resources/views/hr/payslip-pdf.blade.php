<!doctype html>
<html><head><meta charset="utf-8"><title>{{ __('Payslip') }}</title>
<style>
  @page { margin: 28px 32px 60px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #14201a; }
  .hd { border-bottom: 2px solid #14201a; padding-bottom: 10px; margin-bottom: 12px; width: 100%; }
  .hd td { vertical-align: top; }
  h1 { font-size: 16px; margin: 0 0 3px; }
  h2 { font-size: 11px; margin: 12px 0 4px; padding: 4px 6px; background: #e3f5e9; color: #15803d; }
  .muted { color: #5d6d65; }
  table { width: 100%; border-collapse: collapse; }
  .info td { padding: 3px 6px; border-bottom: 1px solid #e3e9e5; }
  .info td.k { color: #5d6d65; width: 22%; }
  .pay th { background: #eef3f0; text-align: left; font-size: 9px; padding: 5px; border-bottom: 1px solid #c9d4cf; }
  .pay td { padding: 5px; border-bottom: 1px solid #e3e9e5; vertical-align: top; }
  .r { text-align: right; }
  .tot td { font-weight: bold; border-top: 1px solid #14201a; }
  .net { margin-top: 12px; padding: 10px; background: #14201a; color: #fff; font-size: 13px; }
  .ft { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #7e9187; }
  .logo { height: 46px; }
</style></head>
<body>
@php
  $d = $s->data; $e = $d['earnings'] ?? []; $x = $d['deductions'] ?? []; $rs = fn ($v) => number_format((float) $v, 2);
  $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
  $earnRows = []; foreach (\App\Models\EmployeeProfile::EARNINGS as $k => $l) { if (($e[$k] ?? 0) > 0) { $earnRows[] = [__($l), $e[$k]]; } }
  if (($d['incentive'] ?? 0) > 0) { $earnRows[] = [__('Incentive'), $d['incentive']]; }
  if (($d['reimbursement'] ?? 0) > 0) { $earnRows[] = [__('Expense claims (TA/DA)'), $d['reimbursement']]; }
  $dedRows = []; foreach (['pf' => 'Provident fund (PF)', 'esi' => 'ESI', 'pt' => 'Professional tax', 'tds' => 'Income tax (TDS)', 'other' => 'Other deduction'] as $k => $l) { if (($x[$k] ?? 0) > 0) { $dedRows[] = [__($l), $x[$k]]; } }
  $rows = max(count($earnRows), count($dedRows), 1);
@endphp
<table class="hd"><tr>
  <td>@if($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif<h1>{{ $c->company_name }}</h1>
    <div class="muted">{{ $c->address }}</div>
    <div class="muted">{{ implode(' · ', array_filter([$c->phone, $c->email, $c->link('website')])) }}</div></td>
  <td class="r"><h1>{{ mb_strtoupper(__('Payslip')) }}</h1><div>{{ $run->month->format('F Y') }}</div>@unless($run->isFinal())<div class="muted">{{ __('Draft') }}</div>@endunless</td>
</tr></table>
<table class="info">
  <tr><td class="k">{{ __('Name') }}</td><td>{{ $d['name'] ?? $s->user->name }}</td><td class="k">{{ __('Employee code') }}</td><td>{{ $d['emp_code'] ?? '—' }}</td></tr>
  <tr><td class="k">{{ __('Designation') }}</td><td>{{ $d['designation'] ?? '—' }}</td><td class="k">{{ __('Headquarter (HQ)') }}</td><td>{{ $d['headquarter'] ?? '—' }}</td></tr>
  <tr><td class="k">{{ __('Date of joining') }}</td><td>{{ ! empty($d['doj']) ? \Illuminate\Support\Carbon::parse($d['doj'])->format('d M Y') : '—' }}</td><td class="k">PAN / UAN</td><td>{{ ! empty($d['pan']) ? substr($d['pan'], 0, 2).'•••••'.substr($d['pan'], -3) : '—' }} / {{ $d['uan'] ?? '—' }}</td></tr>
  <tr><td class="k">{{ __('Bank') }}</td><td>{{ $d['bank'] ?? '—' }} {{ $d['account'] ?? '' }}</td><td class="k">{{ __('Days') }}</td>
    <td>{{ __('Paid :p of :m', ['p' => $n($d['paid_days'] ?? 0), 'm' => $d['days_in_month'] ?? 0]) }}@if(($d['lop_days'] ?? 0) > 0) · {{ __('Loss of pay: :n', ['n' => $n($d['lop_days'])]) }}@endif</td></tr>
</table>
<h2>{{ __('Earnings and deductions (₹)') }}</h2>
<table class="pay">
  <thead><tr><th>{{ __('Earnings') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Deductions') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
  <tbody>
  @for($i = 0; $i < $rows; $i++)
    <tr><td>{{ $earnRows[$i][0] ?? '' }}</td><td class="r">{{ isset($earnRows[$i]) ? $rs($earnRows[$i][1]) : '' }}</td><td>{{ $dedRows[$i][0] ?? '' }}</td><td class="r">{{ isset($dedRows[$i]) ? $rs($dedRows[$i][1]) : '' }}</td></tr>
  @endfor
  <tr class="tot"><td>{{ __('Total earnings') }}</td><td class="r">{{ $rs(($d['gross'] ?? 0) + ($d['incentive'] ?? 0) + ($d['reimbursement'] ?? 0)) }}</td><td>{{ __('Total deductions') }}</td><td class="r">{{ $rs($d['total_deductions'] ?? 0) }}</td></tr>
  </tbody>
</table>
<div class="net">{{ __('Net pay') }}: ₹{{ $rs($d['net'] ?? 0) }} <span style="font-size:9px">({{ $words }})</span></div>
@if(($d['employer']['pf'] ?? 0) || ($d['employer']['esi'] ?? 0))<p class="muted">{{ __('Paid by the company on top: PF ₹:pf, ESI ₹:esi.', ['pf' => $rs($d['employer']['pf'] ?? 0), 'esi' => $rs($d['employer']['esi'] ?? 0)]) }}</p>@endif
@if(! empty($d['note']))<p class="muted">{{ __('Note') }}: {{ $d['note'] }}</p>@endif
<p class="muted">{{ __('This is a computer-generated payslip and needs no signature.') }}</p>
<div class="ft">{{ $c->company_name }} · {{ __('Designed & developed by Solvix Web · Damini Rana · +91 82190 60177') }}</div>
</body></html>
