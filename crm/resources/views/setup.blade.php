@extends('layouts.guest')
@section('title', __('Set up your company'))
@section('authclass', 'wide')
@section('content')
<form method="post" action="{{ route('setup.store') }}" class="form">
  @csrf
  <div><h1>{{ __('Set up your company') }}</h1><p class="muted">{{ __('Enter your company details and create the first admin. All data stays on this server.') }}</p></div>
  @include('partials.flash')
  @if($codeMissing)
    <p class="note bad">{{ __('Setup is locked. Open the .env file on the server, write a secret word of 8 or more characters after CRM_SETUP_CODE=, save, then reload this page.') }}</p>
  @endif
  <div class="field"><label for="setup_code">{{ __('Setup code (from the .env file)') }}</label><input type="password" id="setup_code" name="setup_code" required autocomplete="off"></div>
  <h2>{{ __('Company') }}</h2>
  <div class="f2">
    <div class="field"><label for="company_name">{{ __('Company name') }}</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" required></div>
    <div class="field"><label for="gstin">GSTIN</label><input id="gstin" name="gstin" value="{{ old('gstin') }}"></div>
    <div class="field"><label for="phone">{{ __('Phone') }}</label><input id="phone" name="phone" value="{{ old('phone') }}"></div>
    <div class="field"><label for="address">{{ __('Address') }}</label><input id="address" name="address" value="{{ old('address') }}"></div>
  </div>
  <h2>{{ __('Office timing') }}</h2>
  <div class="f2">
    <div class="field"><label for="office_start">{{ __('Office starts') }}</label><input type="time" id="office_start" name="office_start" value="{{ old('office_start', '09:30') }}" required></div>
    <div class="field"><label for="office_end">{{ __('Office ends') }}</label><input type="time" id="office_end" name="office_end" value="{{ old('office_end', '18:30') }}" required></div>
    <div class="field"><label for="lunch_start">{{ __('Lunch starts') }}</label><input type="time" id="lunch_start" name="lunch_start" value="{{ old('lunch_start', '13:30') }}" required></div>
    <div class="field"><label for="lunch_end">{{ __('Lunch ends') }}</label><input type="time" id="lunch_end" name="lunch_end" value="{{ old('lunch_end', '14:00') }}" required></div>
  </div>
  <h2>{{ __('First admin') }}</h2>
  <div class="f2">
    <div class="field"><label for="admin_name">{{ __('Name') }}</label><input id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required></div>
    <div class="field"><label for="admin_email">{{ __('Email') }}</label><input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required></div>
    <div class="field"><label for="password">{{ __('Password (10+ characters, letters and numbers)') }}</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
    <div class="field"><label for="password_confirmation">{{ __('Password again') }}</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
  </div>
  <button class="btn p">{{ __('Finish setup') }}</button>
</form>
@endsection
