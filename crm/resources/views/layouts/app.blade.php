@php
    $me = auth()->user();
    $company = \App\Models\CompanySetting::current();
    $openClashes = $me->canManage() ? \App\Models\LeadClash::where('status', 'open')->count() : 0;
    $unread = $me->unreadNotifications()->count();
    $nextPunch = app(\App\Services\AttendanceService::class)->nextPunchType($me);
    $is = fn ($r) => request()->routeIs($r) ? 'on' : '';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() === 'hing' ? 'hi-Latn' : app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', __('Dashboard')) · {{ $company->company_name }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="brand">
      @if($company->logo_path)<img src="{{ route('logo') }}" alt="">@else<span class="mark">{{ mb_substr($company->company_name, 0, 1) }}</span>@endif
      <div><b>{{ $company->company_name }}</b><small>{{ __('Sales CRM') }}</small></div>
    </div>
    <nav class="nav">
      <a href="{{ route('dashboard') }}" class="{{ $is('dashboard') }}">{{ __('Dashboard') }}</a>
      <span class="grp">{{ __('Sales') }}</span>
      <a href="{{ route('leads.index') }}" class="{{ $is('leads.*') }}">{{ __('Leads') }}</a>
      <a href="{{ route('targets.index') }}" class="{{ $is('targets.*') }}">{{ __('Targets & sales') }}</a>
      @if($me->canManage())
        <a href="{{ route('clashes.index') }}" class="{{ $is('clashes.*') }}">{{ __('Lead clashes') }} @if($openClashes)<span class="badge">{{ $openClashes }}</span>@endif</a>
      @endif
      <span class="grp">{{ __('Attendance') }}</span>
      <a href="{{ route('attendance.mine') }}" class="{{ $is('attendance.mine') }}">{{ __('My attendance') }}</a>
      @if($me->canManage())
        <a href="{{ route('attendance.today') }}" class="{{ $is('attendance.today') }}">{{ __('Today\'s attendance') }}</a>
        <a href="{{ route('attendance.register') }}" class="{{ $is('attendance.register') }}">{{ __('Monthly register') }}</a>
      @endif
      @if($me->isAdmin())
        <span class="grp">{{ __('Admin') }}</span>
        <a href="{{ route('users.index') }}" class="{{ $is('users.*') }}">{{ __('Users') }}</a>
        <a href="{{ route('settings') }}" class="{{ $is('settings') }}">{{ __('Company settings') }}</a>
        <a href="{{ route('audit') }}" class="{{ $is('audit') }}">{{ __('Activity log') }}</a>
      @endif
    </nav>
  </aside>
  <main class="main">
    <div class="wrap">
      <header class="top">
        <button class="btn menu-btn" data-menu type="button" aria-label="{{ __('Menu') }}">☰</button>
        <form method="post" action="{{ route('attendance.punch') }}" class="punch">
          @csrf
          <input type="hidden" name="type" value="{{ $nextPunch }}">
          <button class="btn {{ $nextPunch === 'in' ? 'good' : 'bad' }}">{{ $nextPunch === 'in' ? __('Punch in') : __('Punch out') }}</button>
        </form>
        <span class="sp"></span>
        <a class="btn" href="{{ route('notifications') }}">{{ __('Alerts') }} @if($unread)<span class="badge">{{ $unread }}</span>@endif</a>
        <a class="btn" href="{{ route('profile') }}">{{ $me->name }}</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn">{{ __('Log out') }}</button></form>
      </header>
      @include('partials.flash')
      @yield('content')
    </div>
  </main>
</div>
</body>
</html>
