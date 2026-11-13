@extends('layouts.guest')
@section('title', __('Log in'))
@section('content')
<form method="post" action="{{ route('login') }}" class="form">
  @csrf
  <div><h1>{{ \App\Models\CompanySetting::current()->company_name }}</h1><p class="muted">{{ __('Log in to the sales CRM') }}</p></div>
  @include('partials.flash')
  <div class="field"><label for="email">{{ __('Email') }}</label><input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
  <div class="field"><label for="password">{{ __('Password') }}</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
  <button class="btn p">{{ __('Log in') }}</button>
</form>
@endsection
