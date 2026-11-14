@extends('layouts.app')
@section('title', __('Masters'))
@section('content')
<div class="head"><div><h1>{{ __('Masters') }}</h1><p>{{ __('Your company\'s own lists. Untick a value to hide it from new entries; old records keep it.') }}</p></div></div>
<div class="grid g2 stretch">
@foreach(\App\Models\MasterOption::TYPES as $type => $label)
  <section class="card">
    <h2>{{ __($label) }}</h2>
    <div class="list">
      @foreach($options->get($type, collect()) as $o)
        <form method="post" action="{{ route('masters.update', $o) }}" class="item">@csrf @method('put')
          <span class="bar {{ $o->active ? '' : 'bad' }}"></span>
          <div class="row"><label class="sr" for="m{{ $o->id }}">{{ __('Name') }}</label><input class="inline-input" id="m{{ $o->id }}" name="value" value="{{ $o->value }}" required maxlength="100">
            <label class="check small"><input type="checkbox" name="active" value="1" @checked($o->active)> {{ __('Active') }}</label></div>
          <button class="btn sm">{{ __('Save') }}</button>
        </form>
      @endforeach
    </div>
    <form method="post" action="{{ route('masters.store') }}" class="row" >@csrf
      <input type="hidden" name="type" value="{{ $type }}">
      <div class="field"><label for="new{{ $type }}">{{ __('Add new') }}</label><input id="new{{ $type }}" name="value" required maxlength="100"></div>
      <button class="btn p"><x-icon name="plus"/>{{ __('Add') }}</button>
    </form>
  </section>
@endforeach
</div>
@endsection
