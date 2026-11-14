@extends('layouts.app')
@section('title', __('Roles & permissions'))
@section('content')
<div class="head"><div><h1>{{ __('Roles & permissions') }}</h1><p>{{ __('Choose what managers and sales reps may do. Admins can always do everything. What people can see never changes: reps see only their own data, managers their team.') }}</p></div></div>
<form method="post" action="{{ route('roles.update') }}" class="card flush form">
  @csrf
  <div class="tbl"><table class="perm">
    <thead><tr><th>{{ __('Permission') }}</th><th class="c">{{ __('Admin') }}</th><th class="c">{{ __('Manager') }}</th><th class="c">{{ __('Sales rep') }}</th></tr></thead>
    <tbody>
    @foreach(\App\Models\CompanySetting::PERMISSIONS as $key => [$label])
      <tr>
        <td><b>{{ __($label) }}</b></td>
        <td><input type="checkbox" checked disabled aria-label="{{ __('Admin') }}"></td>
        @foreach(['manager', 'rep'] as $role)
          <td><input type="checkbox" name="perm[{{ $role }}][{{ $key }}]" value="1" @checked($c->roleAllows($role, $key)) aria-label="{{ __($role === 'rep' ? 'Sales rep' : 'Manager') }}: {{ __($label) }}"></td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="row card-foot"><button class="btn p">{{ __('Save permissions') }}</button></div>
</form>
<section class="card note">
  <h3>{{ __('Always fixed, for safety') }}</h3>
  <p>{{ __('Only admins can add users, change company settings, masters and permissions, and see the activity log. Every export and import is recorded.') }}</p>
</section>
@endsection
