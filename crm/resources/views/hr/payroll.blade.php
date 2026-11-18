@extends('layouts.app')
@section('title', __('Payroll'))
@section('content')
<div class="head"><div><h1>{{ __('Payroll') }}</h1><p>{{ __('Salary from attendance and leave: absent days and leave without pay are cut; weekly offs, holidays and paid leave are paid. PF, ESI, professional tax, TDS and approved expense claims are included.') }}</p></div></div>
@if(! $ready)<p class="flash warn">{{ __('Add salary details under Employees first.') }} <a href="{{ route('hr.employees') }}">{{ __('Employees') }}</a></p>@endif
<form method="post" action="{{ route('hr.payroll.store') }}" class="card form row" id="new-run">@csrf
  <div class="field"><label for="month">{{ __('Month') }}</label><input type="month" id="month" name="month" required value="{{ now()->subMonth()->format('Y-m') }}" max="{{ now()->format('Y-m') }}"></div>
  <button class="btn p">{{ __('Make salary draft') }}</button>
  <span class="small muted">{{ __(':n people have salary details.', ['n' => $ready]) }}</span>
</form>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Month') }}</th><th class="r">{{ __('People') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($runs as $r)
      <tr><td><a href="{{ route('hr.payroll.show', $r) }}"><b>{{ $r->month->format('F Y') }}</b></a></td><td class="r num">{{ $r->payslips_count }}</td>
        <td><span class="pill {{ $r->isFinal() ? 'good' : 'warn' }}">{{ $r->isFinal() ? __('Final') : __('Draft') }}</span></td><td><a class="btn sm" href="{{ route('hr.payroll.show', $r) }}">{{ __('Open') }}</a></td></tr>
    @empty
      <tr><td colspan="4" class="empty">{{ __('No salary made yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
