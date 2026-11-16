@extends('layouts.guest')
@section('title', __('Forgot password?'))
@section('content')
<form method="post" action="{{ route('password.email') }}" class="form">
  @csrf
  <div><h1>{{ __('Forgot password?') }}</h1><p class="muted">{{ __('Enter the email you log in with. We send a link to set a new password. If email is not set up for your company, your admin is told and will reset it.') }}</p></div>
  @include('partials.flash')
  <div class="field"><label for="email">{{ __('Email') }}</label><input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
  <button class="btn p">{{ __('Send the link') }}</button>
  <p class="small center"><a href="{{ route('login') }}">{{ __('Back to log in') }}</a></p>
</form>
@endsection
