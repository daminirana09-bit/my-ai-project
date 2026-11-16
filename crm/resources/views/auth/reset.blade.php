@extends('layouts.guest')
@section('title', __('Set a new password'))
@section('content')
<form method="post" action="{{ route('password.update') }}" class="form">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div><h1>{{ __('Set a new password') }}</h1><p class="muted">{{ __('10 or more characters, with letters and numbers. You are logged out everywhere else.') }}</p></div>
  @include('partials.flash')
  <div class="field"><label for="email">{{ __('Email') }}</label><input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"></div>
  <div class="field"><label for="password">{{ __('New password') }}</label><input type="password" id="password" name="password" required autocomplete="new-password" minlength="10"></div>
  <div class="field"><label for="password_confirmation">{{ __('New password again') }}</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
  <button class="btn p">{{ __('Save new password') }}</button>
</form>
@endsection
