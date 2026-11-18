@extends('layouts.app')
@section('title', __('Approvals'))
@section('content')
@php $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.'); @endphp
<div class="head"><div><h1>{{ __('Approvals') }}</h1><p>{{ __('Leave and expense claims of your team. Approved leave is marked in attendance; approved claims are paid with the salary.') }}</p></div></div>
@error('leave')<p class="flash bad">{{ $message }}</p>@enderror @error('claim')<p class="flash bad">{{ $message }}</p>@enderror
<section class="card flush" id="leaves">
  <div class="ch"><h2>{{ __('Leave waiting') }} ({{ $leaves->count() }})</h2></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Type') }}</th><th class="r">{{ __('Days') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Balance left') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($leaves as $l)
      @php $q = $leaves_service->quota($l->user, $l->type, $l->from_date->year); @endphp
      <tr><td><b>{{ $l->user->name }}</b></td><td>{{ $l->from_date->format('d M') }}@unless($l->from_date->eq($l->to_date)) – {{ $l->to_date->format('d M Y') }}@endunless @if($l->half_day)<span class="sub">{{ __('Half day') }}</span>@endif</td>
        <td>{{ $l->type->name }}</td><td class="r num">{{ $n($l->days) }}</td><td class="small">{{ $l->reason }}</td>
        <td class="small">{{ $q === null ? '—' : $n(max(0, $q - $leaves_service->used($l->user, $l->type, $l->from_date->year, $l->id))) }}</td>
        <td><form method="post" action="{{ route('hr.leaves.decide', $l) }}" class="row decide">@csrf
          <label class="sr" for="ln{{ $l->id }}">{{ __('Note') }}</label><input id="ln{{ $l->id }}" name="note" maxlength="300" placeholder="{{ __('Note (optional)') }}" class="sm">
          <button class="btn sm good" name="decision" value="approve">{{ __('Approve') }}</button><button class="btn sm bad" name="decision" value="reject">{{ __('Reject') }}</button></form></td></tr>
    @empty
      <tr><td colspan="7" class="empty">{{ __('Nothing waiting.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<section class="card flush" id="claims">
  <div class="ch"><h2>{{ __('Claims waiting') }} ({{ $claims->count() }})</h2><span class="small muted">₹{{ number_format((float) $claims->sum('amount'), 2) }}</span></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Date') }}</th><th>{{ __('Expense') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Note') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($claims as $c)
      <tr><td><b>{{ $c->user->name }}</b></td><td>{{ $c->date->format('d M') }}</td>
        <td>{{ __(\App\Models\ExpenseClaim::KINDS[$c->kind]) }}<span class="sub">{{ implode(' → ', array_filter([$c->from_place, $c->to_place])) }}@if($c->km) · {{ $n($c->km) }} km @endif @if($c->kind === 'da' && $c->detail) · {{ __(\App\Models\ExpenseClaim::DA_AREAS[$c->detail] ?? '') }}@endif</span></td>
        <td class="r num">₹{{ number_format((float) $c->amount, 2) }}</td>
        <td class="small">{{ $c->note }} @if($c->receipt_path)<a href="{{ route('hr.claims.receipt', $c) }}" target="_blank" rel="noopener">{{ __('Receipt') }}</a>@endif</td>
        <td><form method="post" action="{{ route('hr.claims.decide', $c) }}" class="row decide">@csrf
          <label class="sr" for="cn{{ $c->id }}">{{ __('Note') }}</label><input id="cn{{ $c->id }}" name="note" maxlength="300" placeholder="{{ __('Note (optional)') }}" class="sm">
          <button class="btn sm good" name="decision" value="approve">{{ __('Approve') }}</button><button class="btn sm bad" name="decision" value="reject">{{ __('Reject') }}</button></form></td></tr>
    @empty
      <tr><td colspan="6" class="empty">{{ __('Nothing waiting.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<div class="grid g2 stretch">
  <section class="card flush"><div class="ch"><h2>{{ __('Recently decided leave') }}</h2></div><div class="list">
    @forelse($recentLeaves as $l)
      <div class="item"><span class="bar {{ $l->status === 'approved' ? 'good' : 'bad' }}"></span><div><b>{{ $l->user->name }} · {{ $l->type->code }} · {{ $n($l->days) }}</b><small>{{ $l->from_date->format('d M') }} – {{ $l->to_date->format('d M') }} · {{ __(\App\Models\LeaveRequest::STATUSES[$l->status]) }} · {{ $l->decider?->name }}</small></div>
        @if($l->status === 'approved')<form method="post" action="{{ route('hr.leaves.cancel', $l) }}">@csrf<button class="btn sm" data-confirm="{{ __('Take back this approved leave? Attendance goes back to the punches.') }}">{{ __('Take back') }}</button></form>@else<span></span>@endif</div>
    @empty<p class="empty">{{ __('Nothing yet.') }}</p>@endforelse
  </div></section>
  <section class="card flush"><div class="ch"><h2>{{ __('Recently decided claims') }}</h2></div><div class="list">
    @forelse($recentClaims as $c)
      <div class="item"><span class="bar {{ $c->status === 'rejected' ? 'bad' : 'good' }}"></span><div><b>{{ $c->user->name }} · ₹{{ number_format((float) $c->amount, 2) }}</b><small>{{ $c->date->format('d M') }} · {{ __(\App\Models\ExpenseClaim::KINDS[$c->kind]) }} · {{ __(\App\Models\ExpenseClaim::STATUSES[$c->status]) }}</small></div><span></span></div>
    @empty<p class="empty">{{ __('Nothing yet.') }}</p>@endforelse
  </div></section>
</div>
@endsection
