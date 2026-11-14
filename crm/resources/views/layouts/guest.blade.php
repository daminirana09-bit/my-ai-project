@php $company = \App\Models\CompanySetting::current(); @endphp
<!doctype html>
<html lang="{{ app()->getLocale() === 'hing' ? 'hi-Latn' : app()->getLocale() }}"@if(in_array(request()->cookie('crm_theme'), ['light', 'dark'], true)) data-theme="{{ request()->cookie('crm_theme') }}"@endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="author" content="Solvix Web">
<title>@yield('title') · {{ $company?->company_name ?? __('Sales CRM') }}</title>
<link rel="icon" href="{{ asset('brand/favicon-32.png') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth @yield('authclass')">
  <aside class="auth-side">
    <div>
      <img src="{{ asset('brand/solvix-logo.png') }}" alt="Solvix Web" width="72" height="72">
      <h2>{{ __('Pharma Sales CRM') }}</h2>
      <p>{{ __('Leads, follow-ups, targets, attendance and your team in one place.') }}</p>
      <ul>
        <li><x-icon name="lock"/>{{ __('Your data stays on your own server') }}</li>
        <li><x-icon name="users"/>{{ __('Each person sees only their own work') }}</li>
        <li><x-icon name="globe"/>English · Hinglish · हिन्दी</li>
      </ul>
    </div>
    <p class="small">{{ __('Designed & developed by') }} <b>Solvix Web</b></p>
  </aside>
  <div class="auth-main">
    <div class="card">@yield('content')</div>
    @include('partials.credit')
  </div>
</div>
</body>
</html>
