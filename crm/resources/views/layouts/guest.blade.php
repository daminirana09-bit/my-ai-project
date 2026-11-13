<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') · {{ \App\Models\CompanySetting::current()?->company_name ?? __('Sales CRM') }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth @yield('authclass')">
  <div class="card">@yield('content')</div>
</div>
</body>
</html>
