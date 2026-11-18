@extends('layouts.app')
@section('title', __('Chatbot'))
@section('content')
@php $try = session('bot_try'); $L = $languages; @endphp
<div class="head"><div><h1>{{ __('Chatbot') }}</h1><p>{{ __('Answers clients on WhatsApp at once, day and night, in their own language. It answers only from your questions & answers, product list and price list, and never makes up a rate. Orders, samples and anything it cannot answer go to the salesperson as a task.') }}</p></div>
  <span class="pill {{ $cfg('enabled') ? 'good' : 'warn' }}">{{ $cfg('enabled') ? __('On') : __('Off') }}</span></div>
@unless($connected)<p class="flash warn">{{ __('WhatsApp is not connected yet (WhatsApp settings). The chatbot starts answering once it is.') }}</p>@endunless
@unless($translator)<p class="flash warn">{{ __('Translation is off: the chatbot answers in English, Hindi and Hinglish only. Turn on translation under WhatsApp settings for every Indian language.') }}</p>@endunless

<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('Chats answered (30 days)') }}</span><span class="v">{{ $stats['chats'] }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Bot replies (30 days)') }}</span><span class="v">{{ $stats['replies'] }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Handed to a person') }}</span><span class="v">{{ $stats['handoffs'] }}</span></div>
</section>

<div class="grid g2 stretch">
  <form method="post" action="{{ route('chatbot.update') }}" class="card form" id="bot-settings">
    @csrf
    <h2>{{ __('Settings') }}</h2>
    <label class="check top"><input type="checkbox" name="enabled" value="1" @checked($cfg('enabled'))><span><b>{{ __('Chatbot on') }}</b><span class="sub muted small">{{ __('Clients get an instant reply with buttons: Price list, Place an order, Talk to a person.') }}</span></span></label>
    <div class="field"><label for="mode">{{ __('When it answers') }}</label><select id="mode" name="mode">@foreach(\App\Services\WhatsApp\ChatBot::MODES as $k => $l)<option value="{{ $k }}" @selected($cfg('mode') === $k)>{{ __($l) }}</option>@endforeach</select></div>
    <div class="field"><label for="welcome">{{ __('Welcome message') }} <span class="small muted">{{ __('({company} = your company name)') }}</span></label><textarea id="welcome" name="welcome" rows="2" maxlength="600">{{ old('welcome', $company->bot_config['welcome'] ?? $cfg('welcome')) }}</textarea></div>
    <label class="check top"><input type="checkbox" name="share_rates" value="1" @checked($cfg('share_rates'))><span><b>{{ __('Tell the rate when a client names a product') }}</b><span class="sub muted small">{{ __('From the product list: composition, packing, rate + GST, minimum order.') }}</span></span></label>
    <label class="check top"><input type="checkbox" name="send_price_list" value="1" @checked($cfg('send_price_list'))><span><b>{{ __('Send the PDF price list when asked for rates') }}</b></span></label>
    <label class="check top"><input type="checkbox" name="ask_details" value="1" @checked($cfg('ask_details'))><span><b>{{ __('Ask a new client for firm name and city') }}</b><span class="sub muted small">{{ __('Filled into the lead automatically.') }}</span></span></label>
    <div class="field"><label for="human_pause_hours">{{ __('After a salesperson replies, the bot stays quiet in that chat for (hours)') }}</label><input type="number" id="human_pause_hours" name="human_pause_hours" min="1" max="72" value="{{ $cfg('human_pause_hours') }}" required></div>
    <div><button class="btn p">{{ __('Save') }}</button></div>
  </form>

  <form method="post" action="{{ route('chatbot.try') }}" class="card form" id="bot-try">
    @csrf
    <h2>{{ __('Try it') }}</h2>
    <p class="small muted">{{ __('Write as a client would. Nothing is sent or saved.') }}</p>
    <div class="f2">
      <div class="field"><label for="try-message">{{ __('Client writes') }}</label><input id="try-message" name="message" maxlength="500" required value="{{ old('message') }}" placeholder="{{ __('e.g. rate list bhejo') }}"></div>
      <div class="field"><label for="try-lang">{{ __('Client\'s language') }}</label><select id="try-lang" name="lang"><option value="">{{ __('Find out from the message') }}</option>@foreach($L as $code => [$en, $native])<option value="{{ $code }}" @selected(old('lang') === $code)>{{ $native }} ({{ $en }})</option>@endforeach</select></div>
    </div>
    <div><button class="btn"><x-icon name="bot"/>{{ __('See the reply') }}</button></div>
    @if($try)
      <div class="bot-try">
        <div class="bubble in"><div class="txt">{{ $try['message'] }}</div><span class="meta">{{ $L[$try['lang']][0] ?? $try['lang'] }}</span></div>
        @forelse($try['replies'] as $r)
          <div class="bubble out bot"><span class="tag">{{ __('Chatbot') }}</span>
            @if(! empty($r['file']))<div class="small"><x-icon name="folder"/> {{ $r['file'] }}</div>@endif
            <div class="txt">{{ $r['text'] }}</div>
            @if($r['buttons'])<div class="bot-buttons">@foreach($r['buttons'] as $b)<span>{{ $b }}</span>@endforeach</div>@endif
          </div>
        @empty
          <p class="small muted">{{ __('No reply: the bot leaves this one to the salesperson.') }}</p>
        @endforelse
      </div>
    @endif
  </form>
</div>

<section class="card form" id="bot-answers">
  <h2>{{ __('Questions & answers') }}</h2>
  <p class="small muted">{{ __('Words to look for (comma separated, any language) and the answer. Write the answer once in English or Hindi; each client gets it in their language. Examples: delivery time, minimum order, payment terms, franchise / PCD, address, timing.') }}</p>
  <form method="post" action="{{ route('chatbot.answers.store') }}" class="f3 bot-add">
    @csrf
    <div class="field"><label for="keywords">{{ __('When the client writes') }}</label><input id="keywords" name="keywords" maxlength="500" required placeholder="{{ __('franchise, pcd, monopoly') }}"></div>
    <div class="field span2"><label for="answer">{{ __('The bot replies') }}</label><textarea id="answer" name="answer" rows="2" maxlength="1500" required placeholder="{{ __('Yes, we give PCD franchise with monopoly rights. Share your city and drug licence number.') }}"></textarea></div>
    <div><button class="btn p"><x-icon name="plus"/>{{ __('Add') }}</button></div>
  </form>
  <div class="list">
    @forelse($answers as $a)
      <form method="post" action="{{ route('chatbot.answers.update', $a) }}" class="f3 bot-row {{ $a->active ? '' : 'off' }}">
        @csrf @method('put')
        <div class="field"><label for="k{{ $a->id }}" class="sr">{{ __('When the client writes') }}</label><input id="k{{ $a->id }}" name="keywords" value="{{ $a->keywords }}" maxlength="500" required></div>
        <div class="field span2"><label for="a{{ $a->id }}" class="sr">{{ __('The bot replies') }}</label><textarea id="a{{ $a->id }}" name="answer" rows="2" maxlength="1500" required>{{ $a->answer }}</textarea></div>
        <div class="row"><label class="check"><input type="checkbox" name="active" value="1" @checked($a->active)> {{ __('On') }}</label>
          <span class="small muted">{{ __('Used :n times', ['n' => $a->hits]) }}</span>
          <button class="btn sm">{{ __('Save') }}</button>
          <button class="btn sm bad" formaction="{{ route('chatbot.answers.destroy', $a) }}" name="_method" value="delete" data-confirm="{{ __('Delete this answer?') }}"><x-icon name="trash"/></button></div>
      </form>
    @empty
      <p class="empty">{{ __('No answers yet. The bot still sends rates, the price list and takes orders.') }}</p>
    @endforelse
  </div>
</section>
@endsection
