@extends('layouts.app')
@section('title', $user->exists ? __('Edit user') : __('Add user'))
@section('content')
<div class="head"><h1>{{ $user->exists ? $user->name : __('Add user') }}</h1></div>
<form method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card form">
  @csrf @if($user->exists) @method('put') @endif
  <div class="f3">
    <div class="field"><label for="name">{{ __('Name') }}</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
    <div class="field"><label for="email">{{ __('Email (login)') }}</label><input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required></div>
    <div class="field"><label for="phone">{{ __('Phone') }}</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}"></div>
    <div class="field"><label for="role">{{ __('Role') }}</label><select id="role" name="role">@foreach(\App\Models\User::ROLES as $k => $l)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ __($l) }}</option>@endforeach</select></div>
    <div class="field"><label for="manager_id">{{ __('Reports to (manager)') }}</label><select id="manager_id" name="manager_id"><option value="">—</option>@foreach($managers as $m)<option value="{{ $m->id }}" @selected((int) old('manager_id', $user->manager_id) === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="field"><label for="locale">{{ __('App language') }}</label><select id="locale" name="locale">@foreach(\App\Models\User::LOCALES as $k => $l)<option value="{{ $k }}" @selected(old('locale', $user->locale) === $k)>{{ $l }}</option>@endforeach</select></div>
  </div>
  <h2>{{ __('Personal timing') }}</h2>
  <p class="small muted">{{ __('Leave empty to use the company timing.') }}</p>
  <div class="f2">
    <div class="field"><label for="office_start">{{ __('Office starts') }}</label><input type="time" id="office_start" name="office_start" value="{{ old('office_start', $user->office_start ? substr($user->office_start, 0, 5) : '') }}"></div>
    <div class="field"><label for="office_end">{{ __('Office ends') }}</label><input type="time" id="office_end" name="office_end" value="{{ old('office_end', $user->office_end ? substr($user->office_end, 0, 5) : '') }}"></div>
    <div class="field"><label for="lunch_start">{{ __('Lunch starts') }}</label><input type="time" id="lunch_start" name="lunch_start" value="{{ old('lunch_start', $user->lunch_start ? substr($user->lunch_start, 0, 5) : '') }}"></div>
    <div class="field"><label for="lunch_end">{{ __('Lunch ends') }}</label><input type="time" id="lunch_end" name="lunch_end" value="{{ old('lunch_end', $user->lunch_end ? substr($user->lunch_end, 0, 5) : '') }}"></div>
  </div>
  <div class="field"><label for="password">{{ $user->exists ? __('New password (leave empty to keep)') : __('Password (10+ characters, letters and numbers)') }}</label><input type="password" id="password" name="password" autocomplete="new-password" @required(! $user->exists)></div>
  <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active ?? true))> {{ __('Can log in (active)') }}</label>
  @if($user->exists)
    <div class="note">
      <h3>{{ __('If this person is leaving') }}</h3>
      <p class="small">{{ __('Untick "active" and choose who gets their leads. They lose access at once and take nothing with them.') }}</p>
      <div class="field"><label for="move_leads_to">{{ __('Move their leads to') }}</label><select id="move_leads_to" name="move_leads_to"><option value="">—</option>@foreach($others ?? [] as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
      @if($user->two_factor_secret)<label class="check"><input type="checkbox" name="reset_2fa" value="1"> {{ __('Reset 2-step login (lost phone)') }}</label>@endif
    </div>
  @endif
  <div class="row"><button class="btn p">{{ __('Save') }}</button><a class="btn" href="{{ route('users.index') }}">{{ __('Cancel') }}</a></div>
</form>
@endsection
