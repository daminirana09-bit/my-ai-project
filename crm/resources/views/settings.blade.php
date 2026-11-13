@extends('layouts.app')
@section('title', __('Company settings'))
@section('content')
@php $days = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 0 => __('Sunday')]; $off = $c->weeklyOffDays(); @endphp
<div class="head"><div><h1>{{ __('Company settings') }}</h1><p>{{ __('Your company\'s own details, logo, office and lunch timing. These apply to everyone unless a person has their own timing.') }}</p></div></div>
<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="form">
  @csrf
  <div class="card form">
    <h2>{{ __('Company details') }}</h2>
    <div class="f3">
      <div class="field"><label for="company_name">{{ __('Company name') }}</label><input id="company_name" name="company_name" value="{{ old('company_name', $c->company_name) }}" required></div>
      <div class="field"><label for="gstin">GSTIN</label><input id="gstin" name="gstin" value="{{ old('gstin', $c->gstin) }}"></div>
      <div class="field"><label for="drug_licence_no">{{ __('Drug licence no.') }}</label><input id="drug_licence_no" name="drug_licence_no" value="{{ old('drug_licence_no', $c->drug_licence_no) }}"></div>
      <div class="field"><label for="phone">{{ __('Phone') }}</label><input id="phone" name="phone" value="{{ old('phone', $c->phone) }}"></div>
      <div class="field"><label for="email">{{ __('Email') }}</label><input type="email" id="email" name="email" value="{{ old('email', $c->email) }}"></div>
      <div class="field"><label for="logo">{{ __('Logo (PNG/JPG, up to 1 MB)') }}</label><input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"></div>
    </div>
    <div class="field"><label for="address">{{ __('Address') }}</label><textarea id="address" name="address">{{ old('address', $c->address) }}</textarea></div>
  </div>
  <div class="card form">
    <h2>{{ __('Office and lunch timing') }}</h2>
    <div class="f3">
      <div class="field"><label for="office_start">{{ __('Office starts') }}</label><input type="time" id="office_start" name="office_start" value="{{ old('office_start', substr($c->office_start, 0, 5)) }}" required></div>
      <div class="field"><label for="office_end">{{ __('Office ends') }}</label><input type="time" id="office_end" name="office_end" value="{{ old('office_end', substr($c->office_end, 0, 5)) }}" required></div>
      <div class="field"><label for="late_grace_minutes">{{ __('Late after (minutes past start)') }}</label><input type="number" min="0" max="180" id="late_grace_minutes" name="late_grace_minutes" value="{{ old('late_grace_minutes', $c->late_grace_minutes) }}" required></div>
      <div class="field"><label for="lunch_start">{{ __('Lunch starts') }}</label><input type="time" id="lunch_start" name="lunch_start" value="{{ old('lunch_start', substr($c->lunch_start, 0, 5)) }}" required></div>
      <div class="field"><label for="lunch_end">{{ __('Lunch ends') }}</label><input type="time" id="lunch_end" name="lunch_end" value="{{ old('lunch_end', substr($c->lunch_end, 0, 5)) }}" required></div>
      <div></div>
      <div class="field"><label for="full_day_hours">{{ __('Full day = at least (hours worked)') }}</label><input type="number" min="1" max="16" step="0.25" id="full_day_hours" name="full_day_hours" value="{{ old('full_day_hours', $c->full_day_minutes / 60) }}" required></div>
      <div class="field"><label for="half_day_hours">{{ __('Half day = at least (hours worked)') }}</label><input type="number" min="0.5" max="12" step="0.25" id="half_day_hours" name="half_day_hours" value="{{ old('half_day_hours', $c->half_day_minutes / 60) }}" required></div>
    </div>
    <p class="small muted">{{ __('Less than the half-day hours counts as absent. Only one punch in a day counts as single swipe. Lunch time is not counted as work.') }}</p>
    <fieldset class="row"><legend class="small muted">{{ __('Weekly off') }}</legend>@foreach($days as $n => $d)<label class="check"><input type="checkbox" name="weekly_off[]" value="{{ $n }}" @checked(in_array($n, $off, true))> {{ $d }}</label>@endforeach</fieldset>
  </div>
  <div class="card form">
    <h2>{{ __('Data protection') }}</h2>
    <label class="check"><input type="checkbox" name="reps_can_export_own_leads" value="1" @checked($c->reps_can_export_own_leads)> {{ __('Allow sales reps to export their own leads') }}</label>
    <p class="small muted">{{ __('Off is safer. Admin and managers can always export. Every export is recorded in the activity log.') }}</p>
  </div>
  <div><button class="btn p">{{ __('Save settings') }}</button></div>
</form>
@endsection
