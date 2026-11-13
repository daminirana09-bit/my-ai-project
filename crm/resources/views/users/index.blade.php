@extends('layouts.app')
@section('title', __('Users'))
@section('content')
<div class="head"><div><h1>{{ __('Users') }}</h1><p>{{ __('Admins see everything. Managers see their team. Sales reps see only their own leads and numbers.') }}</p></div>
  <a class="btn p" href="{{ route('users.create') }}">{{ __('Add user') }}</a></div>
<div class="card"><div class="tbl"><table>
  <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Manager') }}</th><th>{{ __('Language') }}</th><th>{{ __('Timing') }}</th><th class="r">{{ __('Leads') }}</th><th>{{ __('Last login') }}</th><th></th></tr></thead>
  <tbody>
  @foreach($users as $u)
    <tr>
      <td><b>{{ $u->name }}</b>@unless($u->active) <span class="pill bad">{{ __('Inactive') }}</span>@endunless<span class="sub">{{ $u->email }}</span></td>
      <td>{{ __(\App\Models\User::ROLES[$u->role]) }}</td>
      <td>{{ $u->manager?->name ?? '—' }}</td>
      <td>{{ \App\Models\User::LOCALES[$u->locale] ?? $u->locale }}</td>
      <td class="small">{{ $u->office_start ? t12($u->office_start).' – '.t12($u->office_end) : __('Company timing') }}</td>
      <td class="r num">{{ $u->leads_count }}</td>
      <td class="small muted">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
      <td><a class="btn sm" href="{{ route('users.edit', $u) }}">{{ __('Edit') }}</a></td>
    </tr>
  @endforeach
  </tbody>
</table></div></div>
@endsection
