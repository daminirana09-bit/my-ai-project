@extends('layouts.app')
@section('title', __('Leave, claims & payslips'))
@section('content')
@php $p = $me->profile; $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.'); @endphp
<div class="head"><div><h1>{{ __('Leave, claims & payslips') }}</h1>
  <p>{{ $me->name }}@if($p?->designation) · {{ $p->designation }}@endif @if($p?->emp_code) · <span class="mono">{{ $p->emp_code }}</span>@endif @if($p?->headquarter) · {{ __('HQ') }}: {{ $p->headquarter }}@endif</p></div></div>
<div class="chips">
  <a class="chip" href="#leave">{{ __('Leave') }}</a><a class="chip" href="#claims">{{ __('Expense claims (TA/DA)') }}</a><a class="chip" href="#payslips">{{ __('Payslips') }}</a><a class="chip" href="#holidays">{{ __('Holidays') }}</a>
</div>

<section class="kpis hr-bal" id="leave">
  @foreach($balances as $b)
    <div class="card kpi"><span class="l">{{ $b['type']->name }} ({{ $b['type']->code }})</span>
      <span class="v">{{ $b['left'] === null ? '—' : $n($b['left']) }}</span>
      <span class="small muted">@if($b['quota'] === null){{ __(':u taken this year', ['u' => $n($b['used'])]) }}@else{{ __(':u of :q used this year', ['u' => $n($b['used']), 'q' => $n($b['quota'])]) }}@endif</span></div>
  @endforeach
  <div class="card kpi"><span class="l">{{ __('This month') }}</span><span class="v">{{ (int) (($month['full_day'] ?? 0) + ($month['present'] ?? 0)) }}</span>
    <span class="small muted">{{ __('Half days: :h · Absent: :a · Leave: :l', ['h' => (int) ($month['half_day'] ?? 0), 'a' => (int) ($month['absent'] ?? 0), 'l' => (int) ($month['leave'] ?? 0)]) }}</span></div>
</section>

<div class="grid g2 stretch">
  <form method="post" action="{{ route('hr.leaves.store') }}" class="card form" id="leave-form">
    @csrf
    <h2>{{ __('Ask for leave') }}</h2>
    @error('from_date')<p class="flash bad">{{ $message }}</p>@enderror
    <div class="field"><label for="leave_type_id">{{ __('Type of leave') }}</label><select id="leave_type_id" name="leave_type_id" required>@foreach($types as $t)<option value="{{ $t->id }}" @selected((int) old('leave_type_id') === $t->id)>{{ $t->name }}@unless($t->paid) ({{ __('unpaid') }})@endunless</option>@endforeach</select></div>
    <div class="f2">
      <div class="field"><label for="from_date">{{ __('From') }}</label><input type="date" id="from_date" name="from_date" required value="{{ old('from_date') }}"></div>
      <div class="field"><label for="to_date">{{ __('To') }}</label><input type="date" id="to_date" name="to_date" required value="{{ old('to_date') }}"></div>
    </div>
    <label class="check"><input type="checkbox" name="half_day" value="1" @checked(old('half_day'))> {{ __('Half day (one date only)') }}</label>
    <div class="field"><label for="reason">{{ __('Reason') }}</label><textarea id="reason" name="reason" rows="2" maxlength="500" required>{{ old('reason') }}</textarea></div>
    <p class="small muted">{{ __('Weekly offs and holidays inside the dates are not counted. Your manager approves it, and it shows in attendance.') }}</p>
    <div><button class="btn p">{{ __('Send for approval') }}</button></div>
  </form>

  <section class="card flush">
    <div class="ch"><h2>{{ __('My leave') }}</h2></div>
    <div class="tbl"><table>
      <thead><tr><th>{{ __('Dates') }}</th><th>{{ __('Type') }}</th><th class="r">{{ __('Days') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
      <tbody>
      @forelse($myLeaves as $l)
        <tr><td>{{ $l->from_date->format('d M') }}@unless($l->from_date->eq($l->to_date)) – {{ $l->to_date->format('d M') }}@endunless<span class="sub">{{ $l->from_date->format('Y') }}</span></td>
          <td>{{ $l->type->code }}</td><td class="r num">{{ $n($l->days) }}</td>
          <td><span class="pill {{ $l->status }}">{{ __(\App\Models\LeaveRequest::STATUSES[$l->status]) }}</span>@if($l->decision_note)<span class="sub">{{ $l->decision_note }}</span>@endif</td>
          <td>@if($l->status === 'pending')<form method="post" action="{{ route('hr.leaves.cancel', $l) }}">@csrf<button class="btn sm" data-confirm="{{ __('Cancel this leave request?') }}">{{ __('Cancel') }}</button></form>@endif</td></tr>
      @empty
        <tr><td colspan="5" class="empty">{{ __('No leave yet.') }}</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div>

<div class="grid g2 stretch" id="claims">
  <form method="post" action="{{ route('hr.claims.store') }}" class="card form" enctype="multipart/form-data">
    @csrf
    <h2>{{ __('Claim an expense (TA/DA)') }}</h2>
    @foreach(['date', 'amount', 'receipt', 'km', 'area', 'vehicle'] as $f)@error($f)<p class="flash bad">{{ $message }}</p>@enderror @endforeach
    <div class="f2">
      <div class="field"><label for="c_date">{{ __('Date') }}</label><input type="date" id="c_date" name="date" required value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}"></div>
      <div class="field"><label for="kind">{{ __('Expense') }}</label><select id="kind" name="kind" required>@foreach(\App\Models\ExpenseClaim::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ __($l) }}</option>@endforeach</select></div>
    </div>
    <fieldset class="f2"><legend class="small muted">{{ __('For DA') }}</legend>
      <div class="field"><label for="area">{{ __('Worked at') }}</label><select id="area" name="area"><option value="">—</option>@foreach(\App\Models\ExpenseClaim::DA_AREAS as $k => $l)<option value="{{ $k }}" @selected(old('area') === $k)>{{ __($l) }} (₹{{ $n($co->hr('da_'.$k)) }})</option>@endforeach</select></div>
    </fieldset>
    <fieldset class="f2"><legend class="small muted">{{ __('For travel by own vehicle') }}</legend>
      <div class="field"><label for="vehicle">{{ __('Vehicle') }}</label><select id="vehicle" name="vehicle"><option value="">—</option>@foreach(\App\Models\ExpenseClaim::VEHICLES as $k => $l)<option value="{{ $k }}" @selected(old('vehicle') === $k)>{{ __($l) }} (₹{{ $n($co->hr('km_'.$k)) }}/km)</option>@endforeach</select></div>
      <div class="field"><label for="km">{{ __('Kilometres') }}</label><input type="number" id="km" name="km" min="1" max="2000" step="0.1" value="{{ old('km') }}"></div>
    </fieldset>
    <div class="f2">
      <div class="field"><label for="from_place">{{ __('From') }}</label><input id="from_place" name="from_place" maxlength="80" value="{{ old('from_place') }}"></div>
      <div class="field"><label for="to_place">{{ __('To') }}</label><input id="to_place" name="to_place" maxlength="80" value="{{ old('to_place') }}"></div>
      <div class="field"><label for="amount">{{ __('Amount (other expenses)') }}</label><input type="number" id="amount" name="amount" min="1" step="0.01" value="{{ old('amount') }}"></div>
      <div class="field"><label for="receipt">{{ __('Receipt (photo or PDF, up to 5 MB)') }}</label><input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf"></div>
    </div>
    <div class="field"><label for="c_note">{{ __('Note') }}</label><input id="c_note" name="note" maxlength="300" value="{{ old('note') }}" placeholder="{{ __('e.g. Doctor visits in Dewas') }}"></div>
    <p class="small muted">{{ __('DA and travel amounts are worked out from the company rates. Approved claims are paid with the salary.') }}</p>
    <div><button class="btn p">{{ __('Send claim') }}</button></div>
  </form>

  <section class="card flush">
    <div class="ch"><h2>{{ __('My claims') }}</h2></div>
    <div class="tbl"><table>
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Expense') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
      <tbody>
      @forelse($claims as $c)
        <tr><td>{{ $c->date->format('d M') }}</td>
          <td>{{ __(\App\Models\ExpenseClaim::KINDS[$c->kind]) }}<span class="sub">{{ implode(' → ', array_filter([$c->from_place, $c->to_place])) }}@if($c->km) · {{ $n($c->km) }} km @endif @if($c->kind === 'da' && $c->detail) · {{ __(\App\Models\ExpenseClaim::DA_AREAS[$c->detail] ?? '') }}@endif</span></td>
          <td class="r num">₹{{ number_format((float) $c->amount, 2) }}</td>
          <td><span class="pill {{ $c->status }}">{{ __(\App\Models\ExpenseClaim::STATUSES[$c->status]) }}</span>@if($c->decision_note)<span class="sub">{{ $c->decision_note }}</span>@endif</td>
          <td class="row">@if($c->receipt_path)<a class="btn sm" href="{{ route('hr.claims.receipt', $c) }}" target="_blank" rel="noopener">{{ __('Receipt') }}</a>@endif
            @if($c->status === 'pending')<form method="post" action="{{ route('hr.claims.cancel', $c) }}">@csrf<button class="btn sm" data-confirm="{{ __('Remove this claim?') }}">{{ __('Remove') }}</button></form>@endif</td></tr>
      @empty
        <tr><td colspan="5" class="empty">{{ __('No claims yet.') }}</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div>

<div class="grid g2 stretch">
  <section class="card flush" id="payslips">
    <div class="ch"><h2>{{ __('Payslips') }}</h2></div>
    <div class="list">
      @forelse($payslips as $s)
        <div class="item"><span class="bar good"></span><div><b>{{ $s->run->month->format('F Y') }}</b><small>{{ __('Net pay') }}: ₹{{ number_format((float) $s->v('net')) }} · {{ __('Paid days') }}: {{ $n($s->v('paid_days')) }}</small></div>
          <a class="btn sm" href="{{ route('hr.payslip', $s) }}" target="_blank" rel="noopener"><x-icon name="download"/>{{ __('PDF') }}</a></div>
      @empty
        <p class="empty">{{ __('Payslips appear here once the salary for a month is final.') }}</p>
      @endforelse
    </div>
  </section>
  <section class="card flush" id="holidays">
    <div class="ch"><h2>{{ __('Holidays') }}</h2></div>
    <div class="list">
      @forelse($holidays as $h)
        <div class="item"><span class="bar {{ $h->date->isPast() && ! $h->date->isToday() ? '' : 'good' }}"></span><div><b>{{ $h->name }}</b><small>{{ $h->date->translatedFormat('D, d M Y') }} · {{ __(\App\Models\Holiday::TYPES[$h->type]) }}</small></div><span></span></div>
      @empty
        <p class="empty">{{ __('No holidays added yet.') }}</p>
      @endforelse
    </div>
  </section>
</div>
@endsection
