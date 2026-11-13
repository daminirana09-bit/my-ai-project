@extends('layouts.app')
@section('title', __('Today\'s attendance'))
@section('content')
@php $S = \App\Models\AttendanceDay::STATUSES; @endphp
<div class="head"><div><h1>{{ __('Today\'s attendance') }}</h1><p>{{ now()->translatedFormat('l, d F Y') }} · {{ __('Office :from – :to', ['from' => t12($company->office_start), 'to' => t12($company->office_end)]) }}</p></div>
  <a class="btn" href="{{ route('settings') }}">{{ __('Change timings') }}</a></div>
<div class="grid g4">
  <div class="card kpi"><div class="l">{{ __('Working now') }}</div><div class="v">{{ $days->where('status', 'present')->count() }}</div></div>
  <div class="card kpi"><div class="l">{{ __('Late today') }}</div><div class="v">{{ $days->where('late', true)->count() }}</div></div>
  <div class="card kpi"><div class="l">{{ __('Not punched in') }}</div><div class="v">{{ $people->count() - $days->whereNotIn('status', ['absent', 'weekly_off'])->count() }}</div></div>
  <div class="card kpi"><div class="l">{{ __('Single swipe') }}</div><div class="v">{{ $days->where('status', 'single_swipe')->count() }}</div></div>
</div>
<div class="card"><div class="tbl"><table>
  <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Timing') }}</th><th>{{ __('Punch in') }}</th><th>{{ __('Punch out') }}</th><th>{{ __('Worked') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
  <tbody>
  @foreach($people as $p)
    @php $d = $days->get($p->id); $sh = $p->shift($company); @endphp
    <tr>
      <td><b>{{ $p->name }}</b><span class="sub">{{ __(\App\Models\User::ROLES[$p->role]) }}</span></td>
      <td class="nowrap small">{{ t12($sh['office_start']) }} – {{ t12($sh['office_end']) }}@if($p->office_start)<span class="sub">{{ __('Own timing') }}</span>@endif</td>
      <td class="nowrap">{{ $d?->first_in?->format('h:i A') ?? '—' }}</td>
      <td class="nowrap">{{ $d?->last_out?->format('h:i A') ?? '—' }}</td>
      <td class="num">{{ hm($d?->worked_minutes) }}</td>
      <td>@if($d)<span class="pill {{ $d->status }}">{{ __($S[$d->status]) }}</span>@if($d->late) <span class="pill bad">{{ __('Late') }}</span>@endif @else<span class="pill">{{ __('Not punched') }}</span>@endif</td>
      <td><a class="btn sm" href="{{ route('attendance.person', $p) }}">{{ __('Month') }}</a></td>
    </tr>
  @endforeach
  </tbody>
</table></div></div>
@endsection
