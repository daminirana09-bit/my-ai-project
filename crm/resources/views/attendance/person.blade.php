@extends('layouts.app')
@section('title', __('Attendance'))
@section('content')
@php $me = auth()->user(); $S = \App\Models\AttendanceDay::STATUSES; @endphp
<div class="head">
  <div><h1>{{ $person->id === $me->id ? __('My attendance') : __('Attendance: :name', ['name' => $person->name]) }}</h1>
    <p>{{ __('Office :from – :to · Lunch :ls – :le', ['from' => t12($shift['office_start']), 'to' => t12($shift['office_end']), 'ls' => t12($shift['lunch_start']), 'le' => t12($shift['lunch_end'])]) }}</p></div>
  <form method="get" class="row"><label class="sr" for="month">{{ __('Month') }}</label><input type="month" id="month" name="month" value="{{ $start->format('Y-m') }}" data-autosubmit></form>
</div>
<div class="grid g4">
  @foreach(['full_day', 'half_day', 'absent', 'single_swipe'] as $k)
    <div class="card kpi"><div class="l">{{ __($S[$k]) }}</div><div class="v">{{ $summary[$k] ?? 0 }}</div></div>
  @endforeach
</div>
<div class="card">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Punches') }}</th><th>{{ __('Worked') }}</th><th>{{ __('Status') }}</th>@if($me->canManage() && $person->id !== $me->id)<th>{{ __('Change') }}</th>@endif</tr></thead>
    <tbody>
    @for($d = $start; $d->lte($end) && $d->lte(today()); $d = $d->addDay())
      @php $key = $d->toDateString(); $day = $days->get($key); $ps = $punches->get($key, collect()); @endphp
      <tr>
        <td class="nowrap"><b>{{ $d->format('d M') }}</b><span class="sub">{{ $d->translatedFormat('l') }}</span></td>
        <td>@forelse($ps as $p)<span class="pill {{ $p->type === 'in' ? 'good' : 'bad' }}">{{ $p->type === 'in' ? __('In') : __('Out') }} {{ $p->punched_at->format('h:i A') }}</span> @empty<span class="muted">—</span>@endforelse</td>
        <td class="num">{{ hm($day?->worked_minutes) }}</td>
        <td>@if($day)<span class="pill {{ $day->status }}">{{ __($S[$day->status]) }}</span>@if($day->late) <span class="pill bad">{{ __('Late') }}</span>@endif @if($day->manual) <span class="pill">{{ __('Set by admin') }}</span>@endif @if($day->remark)<span class="sub">{{ $day->remark }}</span>@endif @else<span class="muted">—</span>@endif</td>
        @if($me->canManage() && $person->id !== $me->id)
          <td><form method="post" action="{{ route('attendance.override', $person) }}" class="row">@csrf
            <input type="hidden" name="date" value="{{ $key }}">
            <label class="sr" for="o{{ $key }}">{{ __('Status') }}</label>
            <select id="o{{ $key }}" name="status"><option value="auto">{{ __('From punches') }}</option>@foreach(['full_day', 'half_day', 'absent', 'leave', 'weekly_off'] as $k)<option value="{{ $k }}" @selected($day?->manual && $day->status === $k)>{{ __($S[$k]) }}</option>@endforeach</select>
            <label class="sr" for="r{{ $key }}">{{ __('Remark') }}</label><input id="r{{ $key }}" name="remark" placeholder="{{ __('Remark') }}" value="{{ $day?->remark }}">
            <button class="btn sm">{{ __('Save') }}</button></form></td>
        @endif
      </tr>
    @endfor
    </tbody>
  </table></div>
</div>
@endsection
