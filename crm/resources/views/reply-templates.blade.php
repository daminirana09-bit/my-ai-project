@extends('layouts.app')
@section('title', __('Ready replies'))
@section('content')
<div class="head"><div><h1>{{ __('Ready replies') }}</h1><p>{{ __('Replies offered in the Inbox and used for automatic replies, in each client language. {name} = client, {company} = your company, {rep} = salesperson, {back} = when they return. Ask a native speaker to check before going live.') }}</p></div></div>
<div class="chips">@foreach($langs as $k => [$en, $native])<a class="chip {{ $lang === $k ? 'on' : '' }}" href="{{ route('replies.index', ['lang' => $k]) }}">{{ $native }} <b>{{ $counts[$k] ?? 0 }}</b></a>@endforeach</div>
<form method="post" action="{{ route('replies.save') }}" class="card form">
  @csrf <input type="hidden" name="language" value="{{ $lang }}">
  <h2>{{ $langs[$lang][1] }} · {{ $langs[$lang][0] }}</h2>
  @foreach(\App\Models\ReplyTemplate::INTENTS as $k => $l)
    <div class="field"><label for="b{{ $k }}">{{ __($l) }}</label><textarea id="b{{ $k }}" name="body[{{ $k }}]" lang="{{ $lang === 'hing' ? 'hi-Latn' : $lang }}" maxlength="1000">{{ $rows[$k]->body ?? '' }}</textarea></div>
  @endforeach
  <p class="small muted">{{ __('Leave a box empty to use Hindi, then English, for that request.') }}</p>
  <div><button class="btn p">{{ __('Save') }}</button></div>
</form>
@endsection
