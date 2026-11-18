@extends('layouts.app')
@section('title', __('Holidays & HR rules'))
@section('content')
@php $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp
<div class="head"><div><h1>{{ __('Holidays & HR rules') }}</h1><p>{{ __('Holidays are never marked absent. Weekly off days are set under Office hours.') }}</p></div></div>
@if($errors->any())<p class="flash bad">{{ $errors->first() }}</p>@endif
<div class="grid g2 stretch">
  <section class="card form" id="holidays">
    <div class="row"><h2>{{ __('Holidays :y', ['y' => $year]) }}</h2>
      <a class="chip" href="{{ route('hr.settings', ['year' => $year - 1]) }}">← {{ $year - 1 }}</a><a class="chip" href="{{ route('hr.settings', ['year' => $year + 1]) }}">{{ $year + 1 }} →</a></div>
    <form method="post" action="{{ route('hr.holidays.store') }}" class="f3 bot-add">@csrf
      <div class="field"><label for="h_date">{{ __('Date') }}</label><input type="date" id="h_date" name="date" required value="{{ old('date') }}"></div>
      <div class="field"><label for="h_name">{{ __('Holiday') }}</label><input id="h_name" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="{{ __('e.g. Diwali') }}"></div>
      <div class="field"><label for="h_type">{{ __('Type') }}</label><select id="h_type" name="type">@foreach(\App\Models\Holiday::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', 'festival') === $k)>{{ __($l) }}</option>@endforeach</select></div>
      <div><button class="btn p"><x-icon name="plus"/>{{ __('Add') }}</button></div>
    </form>
    <div class="list">
      @forelse($holidays as $h)
        <div class="item"><span class="bar {{ $h->type === 'optional' ? '' : 'good' }}"></span><div><b>{{ $h->name }}</b><small>{{ $h->date->translatedFormat('D, d M Y') }} · {{ __(\App\Models\Holiday::TYPES[$h->type]) }}</small></div>
          <form method="post" action="{{ route('hr.holidays.destroy', $h) }}">@csrf @method('delete')<button class="btn sm bad" data-confirm="{{ __('Remove this holiday?') }}"><x-icon name="trash"/></button></form></div>
      @empty
        <p class="empty">{{ __('No holidays for this year yet.') }}</p>
      @endforelse
    </div>
    <form method="post" action="{{ route('hr.holidays.seed') }}">@csrf<input type="hidden" name="year" value="{{ $year }}"><button class="btn sm">{{ __('Add national holidays for :y', ['y' => $year]) }}</button></form>
    <p class="small muted">{{ __('Festival dates change every year and by state (Holi, Eid, Diwali…): add the ones your company gives.') }}</p>
  </section>

  <form method="post" action="{{ route('hr.settings.update') }}" class="card form" id="rules">
    @csrf
    <h2>{{ __('Daily allowance and travel') }}</h2>
    <div class="f3">
      @foreach(['da_hq' => 'DA at headquarter (₹/day)', 'da_exhq' => 'DA ex-HQ (₹/day)', 'da_outstation' => 'DA outstation (₹/day)', 'km_bike' => 'Two-wheeler (₹/km)', 'km_car' => 'Car (₹/km)'] as $k => $l)
        <div class="field"><label for="{{ $k }}">{{ __($l) }}</label><input type="number" id="{{ $k }}" name="{{ $k }}" min="0" step="0.01" required value="{{ $n($co->hr($k)) }}"></div>
      @endforeach
    </div>
    <h2>{{ __('PF, ESI and professional tax') }}</h2>
    <div class="f3">
      @foreach(['pf_rate' => 'PF % (employee and employer)', 'pf_ceiling' => 'PF on basic up to (₹)', 'esi_rate' => 'ESI % employee', 'esi_employer_rate' => 'ESI % employer', 'esi_ceiling' => 'ESI when gross is up to (₹)', 'pt_default' => 'Professional tax (₹/month, default)'] as $k => $l)
        <div class="field"><label for="{{ $k }}">{{ __($l) }}</label><input type="number" id="{{ $k }}" name="{{ $k }}" min="0" step="0.01" required value="{{ $n($co->hr($k)) }}"></div>
      @endforeach
    </div>
    <p class="small muted">{{ __('Professional tax differs by state; set each person\'s amount on their employee page if it differs.') }}</p>
    <div><button class="btn p">{{ __('Save') }}</button></div>
  </form>
</div>

<section class="card form" id="leave-types">
  <h2>{{ __('Leave types') }}</h2>
  <p class="small muted">{{ __('Days per year; empty = no limit. People who join during the year get their share.') }}</p>
  @foreach($types as $t)
    <form method="post" action="{{ route('hr.leave-types.update', $t) }}" class="f4 bot-row {{ $t->active ? '' : 'off' }}">@csrf @method('put')
      <div class="field"><label for="lc{{ $t->id }}">{{ __('Code') }}</label><input id="lc{{ $t->id }}" name="code" maxlength="10" required value="{{ $t->code }}"></div>
      <div class="field"><label for="ln{{ $t->id }}">{{ __('Name') }}</label><input id="ln{{ $t->id }}" name="name" maxlength="60" required value="{{ $t->name }}"></div>
      <div class="field"><label for="lq{{ $t->id }}">{{ __('Days per year') }}</label><input type="number" id="lq{{ $t->id }}" name="yearly_quota" min="0" max="365" step="0.5" value="{{ $t->yearly_quota === null ? '' : $n($t->yearly_quota) }}"></div>
      <div class="row"><label class="check"><input type="checkbox" name="paid" value="1" @checked($t->paid)> {{ __('Paid') }}</label><label class="check"><input type="checkbox" name="active" value="1" @checked($t->active)> {{ __('On') }}</label><button class="btn sm">{{ __('Save') }}</button></div>
    </form>
  @endforeach
  <form method="post" action="{{ route('hr.leave-types.store') }}" class="f4 bot-add">@csrf
    <div class="field"><label for="new_code">{{ __('Code') }}</label><input id="new_code" name="code" maxlength="10" required placeholder="ML"></div>
    <div class="field"><label for="new_name">{{ __('Name') }}</label><input id="new_name" name="name" maxlength="60" required placeholder="{{ __('e.g. Maternity leave') }}"></div>
    <div class="field"><label for="new_quota">{{ __('Days per year') }}</label><input type="number" id="new_quota" name="yearly_quota" min="0" max="365" step="0.5"></div>
    <div class="row"><label class="check"><input type="checkbox" name="paid" value="1" checked> {{ __('Paid') }}</label><button class="btn p"><x-icon name="plus"/>{{ __('Add') }}</button></div>
  </form>
</section>
@endsection
