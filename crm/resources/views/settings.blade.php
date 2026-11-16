@extends('layouts.app')
@section('title', __('Company settings'))
@section('content')
<div class="head"><div><h1>{{ __('Company settings') }}</h1><p>{{ __('Your company\'s own name, details and logo. They appear in the menu, on the login page and on documents.') }}</p></div></div>
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
  <p class="note">{{ __('Office and lunch timing are under Office hours. Who may export or import is under Roles & permissions.') }}</p>
  <div><button class="btn p">{{ __('Save settings') }}</button></div>
</form>

<form method="post" action="{{ route('settings.mail') }}" class="card form" id="mail">
  @csrf
  <h2>{{ __('Email for "Forgot password"') }}</h2>
  <p class="small muted">{{ __('Your own mailbox sends the password-reset links. Hostinger: create e.g. noreply@yourcompany.com under Emails, server smtp.hostinger.com, port 465. Gmail: smtp.gmail.com, port 465, with an app password. Until this is set, a forgotten password goes to the admin as an alert.') }}</p>
  @foreach(['mail_host', 'mail_port', 'mail_username', 'mail_password'] as $f)@error($f)<p class="flash bad">{{ $message }}</p>@enderror @endforeach
  <div class="f3">
    <div class="field"><label for="mail_host">{{ __('Email server (SMTP)') }}</label><input id="mail_host" name="mail_host" value="{{ old('mail_host', $c->mail('host')) }}" placeholder="smtp.hostinger.com"></div>
    <div class="field"><label for="mail_port">{{ __('Port') }}</label><select id="mail_port" name="mail_port">@foreach([465 => '465 (SSL)', 587 => '587 (TLS)'] as $p => $l)<option value="{{ $p }}" @selected((int) old('mail_port', $c->mail('port', 465)) === $p)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="mail_username">{{ __('Email address (login)') }}</label><input type="email" id="mail_username" name="mail_username" value="{{ old('mail_username', $c->mail('username')) }}" placeholder="noreply@yourcompany.com"></div>
    <div class="field"><label for="mail_password">{{ __('Email password') }}</label><input type="password" id="mail_password" name="mail_password" autocomplete="off" placeholder="{{ $c->mail('password') ? __('Saved. Leave empty to keep.') : '' }}"></div>
    <div class="field"><label for="mail_from">{{ __('Send as (optional)') }}</label><input type="email" id="mail_from" name="mail_from" value="{{ old('mail_from', $c->mail('from')) }}"></div>
  </div>
  <div class="row"><button class="btn p">{{ __('Save') }}</button><button class="btn" formaction="{{ route('settings.mail.test') }}"><x-icon name="mail"/>{{ __('Send a test email to me') }}</button></div>
</form>
@endsection
