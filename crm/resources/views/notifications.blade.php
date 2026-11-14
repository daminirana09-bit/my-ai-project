@extends('layouts.app')
@section('title', __('Alerts'))
@section('content')
<div class="head"><h1>{{ __('Alerts') }}</h1></div>
<div class="card"><div class="list">
  @forelse($list as $n)
    @php $d = $n->data; @endphp
    <div class="item"><span class="bar {{ $n->read_at ? '' : 'bad' }}"></span>
      <div>@if(($d['kind'] ?? '') === 'wa_urgent')<b>{{ __('Urgent WhatsApp: :name', ['name' => $d['name'] ?? '']) }}</b><small>{{ $d['rep'] ?? '' }} · {{ $n->created_at->diffForHumans() }}</small>
        @elseif(($d['kind'] ?? '') === 'catalogue')<b>{{ ($d['updated'] ?? false) ? __('Updated for everyone: :title', ['title' => $d['title'] ?? '']) : __('New file for the team: :title', ['title' => $d['title'] ?? '']) }}</b><small>{{ $d['by'] ?? '' }} · {{ $n->created_at->diffForHumans() }}</small>
        @elseif(($d['kind'] ?? '') === 'new_lead')<b>{{ ($d['again'] ?? false) ? __('Enquiry again: :firm', ['firm' => $d['firm'] ?? '']) : __('New lead: :firm', ['firm' => $d['firm'] ?? '']) }}</b><small>{{ $d['source'] ?? '' }} · {{ $n->created_at->diffForHumans() }}</small>
        @elseif(($d['kind'] ?? '') === 'overdue_follow_ups')<b>{{ __('Follow-ups more than 2 days late') }}</b><small>{{ implode(' · ', $d['lines'] ?? []) }} · {{ $n->created_at->diffForHumans() }}</small>
        @elseif(($d['kind'] ?? '') === 'lead_clash')<b>{{ __('Lead clash: :firm', ['firm' => $d['firm'] ?? '']) }}</b><small>{{ __(':a has it, :b also tried.', ['a' => $d['owner'] ?? '—', 'b' => $d['other'] ?? '—']) }} · {{ $n->created_at->diffForHumans() }}</small>@endif</div>
      @if(($d['kind'] ?? '') === 'lead_clash')<a class="btn sm" href="{{ route('clashes.index') }}">{{ __('Decide') }}</a>@elseif(($d['kind'] ?? '') === 'catalogue')<a class="btn sm" href="{{ route('catalogue.index') }}">{{ __('Open') }}</a>@elseif(($d['kind'] ?? '') === 'new_lead')<a class="btn sm" href="{{ route('leads.show', $d['lead_id'] ?? 0) }}">{{ __('Open') }}</a>@elseif(($d['kind'] ?? '') === 'wa_urgent')<a class="btn sm" href="{{ route('inbox.show', $d['conversation_id'] ?? 0) }}">{{ __('Open') }}</a>@else<span></span>@endif
    </div>
  @empty
    <p class="muted">{{ __('No alerts.') }}</p>
  @endforelse
</div></div>
@endsection
