@extends('layouts.app')
@section('title', __('Office hours'))
@section('content')
@php $days = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 0 => __('Sunday')]; $off = $c->weeklyOffDays(); @endphp
<div class="head"><div><h1>{{ __('Office hours') }}</h1><p>{{ __('Company timing for everyone. A person can have their own timing (set it on their user page). Attendance is marked from these rules.') }}</p></div></div>
<form method="post" action="{{ route('office-hours.update') }}" class="card form">
  @csrf
  <div class="f4">
    <div class="field"><label for="office_start">{{ __('Office starts') }}</label><input type="time" id="office_start" name="office_start" value="{{ old('office_start', substr($c->office_start, 0, 5)) }}" required></div>
    <div class="field"><label for="office_end">{{ __('Office ends') }}</label><input type="time" id="office_end" name="office_end" value="{{ old('office_end', substr($c->office_end, 0, 5)) }}" required></div>
    <div class="field"><label for="lunch_start">{{ __('Lunch starts') }}</label><input type="time" id="lunch_start" name="lunch_start" value="{{ old('lunch_start', substr($c->lunch_start, 0, 5)) }}" required></div>
    <div class="field"><label for="lunch_end">{{ __('Lunch ends') }}</label><input type="time" id="lunch_end" name="lunch_end" value="{{ old('lunch_end', substr($c->lunch_end, 0, 5)) }}" required></div>
    <div class="field"><label for="late_grace_minutes">{{ __('Late after (minutes past start)') }}</label><input type="number" min="0" max="180" id="late_grace_minutes" name="late_grace_minutes" value="{{ old('late_grace_minutes', $c->late_grace_minutes) }}" required></div>
    <div class="field"><label for="full_day_hours">{{ __('Full day = at least (hours worked)') }}</label><input type="number" min="1" max="16" step="0.25" id="full_day_hours" name="full_day_hours" value="{{ old('full_day_hours', $c->full_day_minutes / 60) }}" required></div>
    <div class="field"><label for="half_day_hours">{{ __('Half day = at least (hours worked)') }}</label><input type="number" min="0.5" max="12" step="0.25" id="half_day_hours" name="half_day_hours" value="{{ old('half_day_hours', $c->half_day_minutes / 60) }}" required></div>
  </div>
  <fieldset class="row"><legend class="small muted">{{ __('Weekly off') }}</legend>@foreach($days as $n => $d)<label class="check"><input type="checkbox" name="weekly_off[]" value="{{ $n }}" @checked(in_array($n, $off, true))> {{ $d }}</label>@endforeach</fieldset>
  <p class="small muted">{{ __('Less than the half-day hours counts as absent. Only one punch in a day counts as single swipe. Lunch time is not counted as work.') }}</p>
  <div><button class="btn p">{{ __('Save office hours') }}</button></div>
</form>
<section class="card flush">
  <div class="ch"><h2>{{ __('Timing per person') }}</h2></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Office') }}</th><th>{{ __('Lunch') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($people as $p)
      @php $sh = $p->shift($c); @endphp
      <tr><td><b>{{ $p->name }}</b>@if($p->office_start)<span class="sub">{{ __('Own timing') }}</span>@else<span class="sub">{{ __('Company timing') }}</span>@endif</td>
        <td class="nowrap">{{ t12($sh['office_start']) }} – {{ t12($sh['office_end']) }}</td><td class="nowrap">{{ t12($sh['lunch_start']) }} – {{ t12($sh['lunch_end']) }}</td>
        <td class="r"><a class="btn sm" href="{{ route('users.edit', $p) }}"><x-icon name="edit"/>{{ __('Change') }}</a></td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endsection
