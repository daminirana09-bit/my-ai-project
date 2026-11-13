@extends('layouts.guest')
@section('title', __('2-step login'))
@section('content')
<form method="post" action="{{ route('login.otp') }}" class="form">
  @csrf
  <div><h1>{{ __('2-step login') }}</h1><p class="muted">{{ __('Enter the 6-digit code from your authenticator app.') }}</p></div>
  @include('partials.flash')
  <div class="field"><label for="code">{{ __('Code') }}</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code"></div>
  <button class="btn p">{{ __('Verify') }}</button>
</form>
@endsection
