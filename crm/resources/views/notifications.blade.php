@extends('layouts.app')
@section('title', __('Alerts'))
@section('content')
<div class="head"><h1>{{ __('Alerts') }}</h1></div>
<div class="card"><div class="list">
  @forelse($list as $n)
    @php $d = $n->data; @endphp
    <div class="item"><span class="bar {{ $n->read_at ? '' : 'bad' }}"></span>
      <div>@if(($d['kind'] ?? '') === 'lead_clash')<b>{{ __('Lead clash: :firm', ['firm' => $d['firm'] ?? '']) }}</b><small>{{ __(':a has it, :b also tried.', ['a' => $d['owner'] ?? '—', 'b' => $d['other'] ?? '—']) }} · {{ $n->created_at->diffForHumans() }}</small>@endif</div>
      @if(($d['kind'] ?? '') === 'lead_clash')<a class="btn sm" href="{{ route('clashes.index') }}">{{ __('Decide') }}</a>@endif
    </div>
  @empty
    <p class="muted">{{ __('No alerts.') }}</p>
  @endforelse
</div></div>
@endsection
