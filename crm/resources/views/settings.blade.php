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
@endsection
