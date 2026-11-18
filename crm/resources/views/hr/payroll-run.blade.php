@extends('layouts.app')
@section('title', __('Salary :m', ['m' => $run->month->format('M Y')]))
@section('content')
@php $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.'); $rs = fn ($v) => '₹'.number_format((float) $v); @endphp
<div class="head"><div><h1>{{ __('Salary :m', ['m' => $run->month->format('F Y')]) }} <span class="pill {{ $run->isFinal() ? 'good' : 'warn' }}">{{ $run->isFinal() ? __('Final') : __('Draft') }}</span></h1>
  <p>@if($run->isFinal()){{ __('Finalised on :d. Everyone can see their payslip.', ['d' => $run->finalized_at?->format('d M Y')]) }}@else{{ __('Check paid days, add incentive or other deduction, then finalise.') }}@endif</p></div>
  <div class="row">
    <a class="btn" href="{{ route('hr.payroll.export', [$run, 'type' => 'register']) }}"><x-icon name="download"/>{{ __('Salary register (Excel)') }}</a>
    <a class="btn" href="{{ route('hr.payroll.export', [$run, 'type' => 'bank']) }}"><x-icon name="download"/>{{ __('Bank transfer sheet') }}</a>
    @unless($run->isFinal())
      <form method="post" action="{{ route('hr.payroll.recalculate', $run) }}">@csrf<button class="btn">{{ __('Recalculate') }}</button></form>
      <form method="post" action="{{ route('hr.payroll.finalize', $run) }}">@csrf<button class="btn p" data-confirm="{{ __('Finalise salary for this month? It cannot be changed afterwards.') }}">{{ __('Finalise') }}</button></form>
    @endunless
  </div></div>
@error('run')<p class="flash bad">{{ $message }}</p>@enderror
@if(! $run->isFinal() && $run->month->copy()->endOfMonth()->isFuture())<p class="flash warn">{{ __('This month is not over yet: days after today are counted as worked. Make the final salary after the month ends.') }}</p>@endif
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('People') }}</span><span class="v">{{ $slips->count() }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Gross') }}</span><span class="v">{{ $rs($totals['gross']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Deductions') }}</span><span class="v">{{ $rs($totals['deductions']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Expense claims') }}</span><span class="v">{{ $rs($totals['reimbursement']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Net to pay') }}</span><span class="v">{{ $rs($totals['net']) }}</span></div>
  <div class="card kpi"><span class="l">{{ __('PF + ESI to deposit') }}</span><span class="v">{{ $rs($totals['pf'] + $totals['esi']) }}</span></div>
</section>
<section class="card flush">
  <div class="tbl"><table class="payroll">
    <thead><tr><th>{{ __('Person') }}</th><th class="r">{{ __('Paid days') }}</th><th class="r">{{ __('Gross') }}</th><th class="r">{{ __('Claims') }}</th><th class="r">{{ __('Deductions') }}</th><th class="r">{{ __('Net') }}</th><th>{{ __('Incentive / other deduction') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($slips as $s)
      @php $d = $s->v('deductions', []); @endphp
      <tr><td><b>{{ $s->user->name }}</b><span class="sub">{{ $s->v('emp_code') }} {{ $s->v('designation') }}</span></td>
        <td class="r num">{{ $n($s->v('paid_days')) }}/{{ $s->v('days_in_month') }}@if($s->v('lop_days') > 0)<span class="sub bad-t">{{ __('LOP :n', ['n' => $n($s->v('lop_days'))]) }}</span>@endif @unless($s->v('uses_attendance'))<span class="sub">{{ __('no punches') }}</span>@endunless</td>
        <td class="r num">{{ $rs($s->v('gross')) }}</td><td class="r num">{{ $rs($s->v('reimbursement')) }}</td>
        <td class="r num">{{ $rs($s->v('total_deductions')) }}<span class="sub">PF {{ $d['pf'] ?? 0 }} · ESI {{ $d['esi'] ?? 0 }} · PT {{ $d['pt'] ?? 0 }}@if($d['tds'] ?? 0) · TDS {{ $d['tds'] }}@endif</span></td>
        <td class="r num"><b>{{ $rs($s->v('net')) }}</b></td>
        <td>@if($run->isFinal()){{ $s->v('incentive') ? __('Incentive').' '.$rs($s->v('incentive')) : '' }} {{ ($d['other'] ?? 0) ? __('Other').' '.$rs($d['other']) : '' }}
          @else<form method="post" action="{{ route('hr.payroll.adjust', [$run, $s]) }}" class="row adj">@csrf
            <label class="sr" for="inc{{ $s->id }}">{{ __('Incentive') }}</label><input type="number" id="inc{{ $s->id }}" name="incentive" min="0" step="1" value="{{ $s->adjust['incentive'] ?? '' }}" placeholder="{{ __('Incentive') }}" class="sm">
            <label class="sr" for="ded{{ $s->id }}">{{ __('Other deduction') }}</label><input type="number" id="ded{{ $s->id }}" name="deduction" min="0" step="1" value="{{ $s->adjust['deduction'] ?? '' }}" placeholder="{{ __('Deduction') }}" class="sm">
            <label class="sr" for="note{{ $s->id }}">{{ __('Note') }}</label><input id="note{{ $s->id }}" name="note" maxlength="200" value="{{ $s->adjust['note'] ?? '' }}" placeholder="{{ __('Note') }}" class="sm">
            <button class="btn sm">{{ __('Save') }}</button></form>@endif</td>
        <td><a class="btn sm" href="{{ route('hr.payslip', $s) }}" target="_blank" rel="noopener">{{ __('Payslip') }}</a></td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@unless($run->isFinal())<form method="post" action="{{ route('hr.payroll.destroy', $run) }}">@csrf @method('delete')<button class="btn sm bad" data-confirm="{{ __('Delete this draft?') }}">{{ __('Delete draft') }}</button></form>@endunless
@endsection
