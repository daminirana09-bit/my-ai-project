@extends('layouts.app')
@section('title', __('WhatsApp inbox'))
@section('content')
@php $me = auth()->user(); $L = $languages; @endphp
<div class="head"><div><h1>{{ __('WhatsApp inbox') }}</h1><p>{{ __('Chats on your WhatsApp numbers. The client\'s language and request are detected, and ready replies are shown in that language.') }}</p></div></div>
<div class="chips">
  @foreach([null => 'All', 'unread' => 'Unread', 'callback' => 'Waiting for call', 'urgent' => 'Urgent'] as $k => $l)
    <a class="chip {{ $filter === $k ? 'on' : '' }}" href="{{ route('inbox', array_filter(['show' => $k])) }}">{{ __($l) }}</a>
  @endforeach
</div>
<div class="inbox-grid">
  <section class="card flush conv-list">
    @forelse($list as $c)
      <a class="conv {{ $current?->id === $c->id ? 'on' : '' }}" href="{{ route('inbox.show', array_filter(['conversation' => $c->id, 'show' => $filter])) }}">
        <span class="t"><b>{{ $c->lead?->firm_name ?? $c->contact_name ?? $c->maskedNumber() }}</b>@if($c->unread)<span class="count">{{ $c->unread }}</span>@endif</span>
        <small>{{ $L[$c->language][1] ?? '—' }} · {{ __(\App\Models\ReplyTemplate::INTENTS[$c->last_intent] ?? 'Other') }} · {{ $c->last_message_at?->diffForHumans() }}</small>
        <span class="row">@if($c->urgent)<span class="pill bad">{{ __('Urgent') }}</span>@endif @if($c->waiting_callback)<span class="pill warn">{{ __('Waiting for call') }}</span>@endif<span class="pill">{{ $c->number?->label }}</span></span>
      </a>
    @empty
      <p class="empty">{{ __('No chats yet. Messages appear here once WhatsApp is connected under WhatsApp settings.') }}</p>
    @endforelse
  </section>

  <section class="card flush chat">
    @if($current)
      <div class="ch chat-h">
        <div><h2>{{ $current->lead?->firm_name ?? $current->contact_name ?? __('Client') }}</h2>
          <span class="small muted"><span class="mono">{{ $current->maskedNumber() }}</span> · {{ __('Language') }}: <b>{{ $L[$current->language][1] ?? '—' }}</b> ({{ $L[$current->language][0] ?? '' }}) · {{ __('to') }} {{ $current->number?->label }}</span></div>
        <div class="row">
          @if($current->lead_id)<a class="btn sm" href="{{ route('leads.show', $current->lead_id) }}">{{ __('Open lead') }}</a><a class="btn sm" href="{{ route('quotes.create', ['lead' => $current->lead_id]) }}">{{ __('New quote') }}</a>@endif
          @if($current->waiting_callback || $current->urgent)<form method="post" action="{{ route('inbox.done', $current) }}">@csrf<button class="btn sm good"><x-icon name="check"/>{{ __('Handled') }}</button></form>@endif
        </div>
      </div>
      <div class="msgs">
        @foreach($current->messages as $m)
          <div class="bubble {{ $m->direction }} {{ $m->type === 'auto' ? 'auto' : '' }}">
            @if($m->type === 'auto')<span class="tag">{{ __('Automatic reply') }}</span>@endif
            <div class="txt" lang="{{ $m->language === 'hing' ? 'hi-Latn' : ($m->language ?: 'en') }}">{{ $m->body }}</div>
            <span class="meta">{{ $m->sent_at->format('d M, g:i A') }}@if($m->direction === 'in' && $m->intent && $m->intent !== 'other') · {{ __(\App\Models\ReplyTemplate::INTENTS[$m->intent]) }}@endif @if($m->direction === 'out') · {{ $m->user?->name ?? __('Automatic') }} · {{ __(ucfirst($m->status)) }}@endif</span>
            @if($m->error)<span class="meta err">{{ $m->error }}</span>@endif
          </div>
        @endforeach
      </div>
      <div class="chat-foot">
        @if($current->canReplyFreely())
          @if($suggestions->isNotEmpty())
            <h3>{{ __('Ready replies in :lang', ['lang' => $L[$current->language][0] ?? 'English']) }}</h3>
            <div class="sugs">
              @foreach($suggestions as $i => $s)
                <form method="post" action="{{ route('inbox.send', $current) }}" class="sug">@csrf
                  <input type="hidden" name="body" value="{{ $s->text }}">
                  <span class="pill">{{ __(\App\Models\ReplyTemplate::INTENTS[$s->intent]) }}</span>
                  <div class="o">{{ $s->text }}</div>
                  @if($s->meaning)<div class="m">{{ __('Meaning') }}: {{ $s->meaning }}</div>@endif
                  <button class="btn sm p">{{ __('Send') }}</button>
                </form>
              @endforeach
            </div>
          @endif
          <form method="post" action="{{ route('inbox.send', $current) }}" class="row compose">@csrf
            <label class="sr" for="body">{{ __('Your reply') }}</label>
            <textarea id="body" name="body" rows="2" maxlength="4000" required placeholder="{{ __('Type a reply…') }}">{{ old('body') }}</textarea>
            <button class="btn p">{{ __('Send') }}</button>
          </form>
        @else
          <p class="note">{{ __('More than 24 hours since the client\'s last message, so WhatsApp only allows an approved template. Use Marketing to send one, or call the client.') }}</p>
        @endif
      </div>
    @else
      <p class="empty">{{ __('Choose a chat on the left.') }}</p>
    @endif
  </section>

  <div class="grid">
    @include('partials.availability')
  </div>
</div>
@endsection
