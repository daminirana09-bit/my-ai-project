@extends('layouts.app')
@section('title', __('My profile'))
@section('content')
@php $me = auth()->user(); @endphp
<div class="head"><h1>{{ __('My profile') }}</h1></div>
@include('partials.availability')
<div class="grid g3">
  <form method="post" action="{{ route('profile.language') }}" class="card form">
    @csrf
    <h2>{{ __('Language') }}</h2>
    <div class="field"><label for="locale">{{ __('Show the app in') }}</label><select id="locale" name="locale" data-autosubmit>@foreach(\App\Models\User::LOCALES as $k => $l)<option value="{{ $k }}" @selected($me->locale === $k)>{{ $l }}</option>@endforeach</select></div>
  </form>
  <form method="post" action="{{ route('profile.theme') }}" class="card form">
    @csrf
    <h2>{{ __('Colour theme') }}</h2>
    <div class="field"><label for="theme">{{ __('Show the app in') }}</label><select id="theme" name="theme" data-autosubmit>@foreach(\App\Models\User::THEMES as $k => $l)<option value="{{ $k }}" @selected($me->theme === $k)>{{ __($l) }}</option>@endforeach</select></div>
  </form>
  <form method="post" action="{{ route('profile.password') }}" class="card form">
    @csrf
    <h2>{{ __('Change password') }}</h2>
    <div class="field"><label for="current_password">{{ __('Current password') }}</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
    <div class="field"><label for="password">{{ __('New password') }}</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
    <div class="field"><label for="password_confirmation">{{ __('New password again') }}</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
    <div><button class="btn">{{ __('Change password') }}</button></div>
  </form>
  <div class="card form">
    <h2>{{ __('2-step login') }}</h2>
    @if($me->two_factor_secret)
      <p><span class="pill good">{{ __('On') }}</span> {{ __('You enter a code from your authenticator app at every login.') }}</p>
      <form method="post" action="{{ route('profile.2fa.disable') }}" class="form">@csrf
        <div class="field"><label for="dpw">{{ __('Password to turn it off') }}</label><input type="password" id="dpw" name="password" required></div>
        <div><button class="btn bad">{{ __('Turn off') }}</button></div></form>
    @elseif($pendingSecret)
      <p class="small">{{ __('In Google Authenticator or Microsoft Authenticator choose "Enter a setup key" and type this key:') }}</p>
      <p class="secret">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</p>
      <form method="post" action="{{ route('profile.2fa.confirm') }}" class="form">@csrf
        <div class="field"><label for="code">{{ __('Code shown in the app') }}</label><input id="code" name="code" inputmode="numeric" maxlength="6" required></div>
        <div><button class="btn p">{{ __('Turn on') }}</button></div></form>
    @else
      <p class="small muted">{{ __('Recommended. Even if someone learns your password, they cannot log in without your phone.') }}</p>
      <form method="post" action="{{ route('profile.2fa.start') }}">@csrf<button class="btn p">{{ __('Set up') }}</button></form>
    @endif
  </div>
</div>
@endsection
