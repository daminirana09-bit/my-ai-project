@php
    $me = auth()->user();
    $company = \App\Models\CompanySetting::current();
    $openClashes = $me->canManage() ? \App\Models\LeadClash::where('status', 'open')
        ->when(! $me->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->whereIn('existing_owner_id', $me->visibleUserIds())->orWhereIn('other_user_id', $me->visibleUserIds())))->count() : 0;
    $myTasksDue = \App\Models\Task::where('assigned_to', $me->id)->where('status', '!=', 'done')->whereNotNull('due_on')->whereDate('due_on', '<=', today())->count();
    $myFollowUps = \App\Models\Lead::where('owner_id', $me->id)->open()->whereDate('next_follow_up_on', '<=', today())->count();
    $unread = $me->unreadNotifications()->count();
    $chatUnread = (int) \App\Models\WaConversation::visibleTo($me)->sum('unread');
    $nextPunch = app(\App\Services\AttendanceService::class)->nextPunchType($me);
    $on = fn (string ...$r) => request()->routeIs(...$r) ? 'on' : '';
    $initials = collect(explode(' ', $me->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() === 'hing' ? 'hi-Latn' : app()->getLocale() }}"@if(in_array(auth()->user()?->theme, ['light', 'dark'], true)) data-theme="{{ auth()->user()->theme }}"@endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="author" content="Solvix Web">
<title>@yield('title', __('Dashboard')) · {{ $company->company_name }}</title>
<link rel="icon" href="{{ asset('brand/favicon-32.png') }}">
<link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
<script src="{{ asset_v('js/app.js') }}" defer></script>
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="sb-brand">
      @if($company->logo_path)<img src="{{ route('logo') }}" alt="">@else<span class="mark">{{ mb_strtoupper(mb_substr($company->company_name, 0, 1)) }}</span>@endif
      <div><b>{{ $company->company_name }}</b><small>{{ __('Sales CRM') }}</small></div>
    </div>
    <nav class="sb-nav" aria-label="{{ __('Menu') }}">
      <a class="sb-link {{ $on('dashboard') }}" href="{{ route('dashboard') }}"><x-icon name="dashboard"/>{{ __('Dashboard') }}</a>

      <a class="sb-link {{ $on('inbox', 'inbox.*') }}" href="{{ route('inbox') }}"><x-icon name="chat"/><span class="sp">{{ __('WhatsApp inbox') }}</span>@if($chatUnread)<span class="count">{{ $chatUnread }}</span>@endif</a>

      <span class="sb-grp">{{ __('Sales') }}</span>
      <a class="sb-link {{ $on('leads.*') }}" href="{{ route('leads.index') }}"><x-icon name="leads"/><span class="sp">{{ __('Leads') }}</span>@if($myFollowUps)<span class="count soft" title="{{ __('Follow-ups due') }}">{{ $myFollowUps }}</span>@endif</a>
      <a class="sb-link {{ $on('customers.*') }}" href="{{ route('customers.index') }}"><x-icon name="customers"/>{{ __('Customers') }}</a>
      <a class="sb-link {{ $on('tasks.*') }}" href="{{ route('tasks.index') }}"><x-icon name="tasks"/><span class="sp">{{ __('Tasks') }}</span>@if($myTasksDue)<span class="count">{{ $myTasksDue }}</span>@endif</a>
      <a class="sb-link {{ $on('quotes.*') }}" href="{{ route('quotes.index') }}"><x-icon name="audit"/>{{ __('Quotations') }}</a>
      <a class="sb-link {{ $on('targets.*') }}" href="{{ route('targets.index') }}"><x-icon name="target"/>{{ __('Targets & orders') }}</a>
      @if($me->canManage())
        <a class="sb-link {{ $on('clashes.*') }}" href="{{ route('clashes.index') }}"><x-icon name="clash"/><span class="sp">{{ __('Lead clashes') }}</span>@if($openClashes)<span class="count">{{ $openClashes }}</span>@endif</a>
      @endif
      <a class="sb-link {{ $on('products.*') }}" href="{{ route('products.index') }}"><x-icon name="products"/>{{ __('Products') }}</a>
      @if($me->allows('view_analytics'))
        <a class="sb-link {{ $on('analytics') }}" href="{{ route('analytics') }}"><x-icon name="analytics"/>{{ __('Analytics') }}</a>
      @endif
      @if($me->canManage() && $me->allows('send_campaigns'))
        <a class="sb-link {{ $on('campaigns.*') }}" href="{{ route('campaigns.index') }}"><x-icon name="megaphone"/>{{ __('Marketing') }}</a>
      @endif
      @if($me->canManage())
        <a class="sb-link {{ $on('replies.*') }}" href="{{ route('replies.index') }}"><x-icon name="globe"/>{{ __('Ready replies') }}</a>
      @endif

      <span class="sb-grp">{{ __('Attendance') }}</span>
      <a class="sb-link {{ $on('attendance.mine') }}" href="{{ route('attendance.mine') }}"><x-icon name="clock"/>{{ __('My attendance') }}</a>
      @if($me->canManage())
        <a class="sb-link {{ $on('attendance.today', 'attendance.person') }}" href="{{ route('attendance.today') }}"><x-icon name="users"/>{{ __('Today\'s attendance') }}</a>
        <a class="sb-link {{ $on('attendance.register') }}" href="{{ route('attendance.register') }}"><x-icon name="calendar"/>{{ __('Monthly register') }}</a>
      @endif
      @if($me->isAdmin())
        <a class="sb-link {{ $on('office-hours') }}" href="{{ route('office-hours') }}"><x-icon name="office"/>{{ __('Office hours') }}</a>

        <span class="sb-grp">{{ __('Admin') }}</span>
        <a class="sb-link {{ $on('users.*') }}" href="{{ route('users.index') }}"><x-icon name="users"/>{{ __('Users') }}</a>
        <a class="sb-link {{ $on('roles') }}" href="{{ route('roles') }}"><x-icon name="shield"/>{{ __('Roles & permissions') }}</a>
        <a class="sb-link {{ $on('integrations.*') }}" href="{{ route('integrations.index') }}"><x-icon name="plug"/>{{ __('Lead integrations') }}</a>
        <a class="sb-link {{ $on('whatsapp.*') }}" href="{{ route('whatsapp.settings') }}"><x-icon name="chat"/>{{ __('WhatsApp settings') }}</a>
        <a class="sb-link {{ $on('automations') }}" href="{{ route('automations') }}"><x-icon name="bolt"/>{{ __('Automations') }}</a>
        <a class="sb-link {{ $on('masters.*') }}" href="{{ route('masters.index') }}"><x-icon name="list"/>{{ __('Masters') }}</a>
        <a class="sb-link {{ $on('settings') }}" href="{{ route('settings') }}"><x-icon name="settings"/>{{ __('Company settings') }}</a>
        <a class="sb-link {{ $on('audit') }}" href="{{ route('audit') }}"><x-icon name="audit"/>{{ __('Activity log') }}</a>
        <a class="sb-link {{ $on('system') }}" href="{{ route('system') }}"><x-icon name="check"/>{{ __('System check') }}</a>
      @endif
    </nav>
    <div class="sb-foot">
      <img src="{{ asset('brand/solvix-icon-64.png') }}" alt="Solvix Web" width="34" height="34">
      <div class="who">{{ __('Designed & developed by') }}<b>Solvix Web</b></div>
    </div>
  </aside>

  <div class="main-col">
    <header class="topbar">
      <button class="btn menu-btn" data-menu type="button" aria-label="{{ __('Menu') }}"><x-icon name="menu"/></button>
      <form class="search" method="get" action="{{ route('search') }}" role="search">
        <label class="sr" for="gq">{{ __('Search') }}</label>
        <input id="gq" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('Search name, email, phone, GSTIN, product…') }}" autocomplete="off">
        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search"/></button>
      </form>
      <span class="sp"></span>
      <form method="post" action="{{ route('attendance.punch') }}">
        @csrf
        <input type="hidden" name="type" value="{{ $nextPunch }}">
        <button class="btn {{ $nextPunch === 'in' ? 'good' : 'bad' }}"><x-icon name="clock"/>{{ $nextPunch === 'in' ? __('Punch in') : __('Punch out') }}</button>
      </form>
      <form method="post" action="{{ route('profile.theme') }}" data-theme-toggle>@csrf<input type="hidden" name="theme" value="{{ $me->theme === 'dark' ? 'light' : 'dark' }}"><button class="iconbtn" aria-label="{{ __('Light / dark') }}" title="{{ __('Light / dark') }}"><span class="i-moon"><x-icon name="moon"/></span><span class="i-sun"><x-icon name="sun"/></span></button></form>
      <a class="iconbtn" href="{{ route('notifications') }}" aria-label="{{ __('Alerts') }}" title="{{ __('Alerts') }}"><x-icon name="bell"/>@if($unread)<span class="count">{{ $unread }}</span>@endif</a>
      <a class="userchip" href="{{ route('profile') }}" title="{{ __('My profile') }}"><span class="avatar">{{ mb_strtoupper($initials) }}</span><span><b>{{ $me->name }}</b><small>{{ __(\App\Models\User::ROLES[$me->role]) }}</small></span></a>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="iconbtn" aria-label="{{ __('Log out') }}" title="{{ __('Log out') }}"><x-icon name="logout"/></button></form>
    </header>
    <main class="content">
      <div class="wrap">
        @include('partials.flash')
        @yield('content')
        @include('partials.credit')
      </div>
    </main>
  </div>
</div>
</body>
</html>
