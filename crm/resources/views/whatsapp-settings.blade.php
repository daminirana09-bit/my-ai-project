@extends('layouts.app')
@section('title', __('WhatsApp settings'))
@section('content')
@php $hook = url('/webhook/whatsapp'); @endphp
<div class="head"><div><h1>{{ __('WhatsApp settings') }}</h1><p>{{ __('Connect your WhatsApp Business account (Meta Cloud API) and give each salesperson their own number. Keys are stored encrypted and never shown again.') }}</p></div></div>
<div class="grid g2 stretch">
  <form method="post" action="{{ route('whatsapp.update') }}" class="card form">
    @csrf
    <h2>{{ __('Meta connection') }}</h2>
    <div class="field"><label for="access_token">{{ __('Permanent access token (System user)') }}</label><input type="password" id="access_token" name="access_token" autocomplete="off" placeholder="{{ $c->wa('access_token') ? __('Saved. Leave empty to keep.') : '' }}"></div>
    <div class="field"><label for="app_secret">{{ __('App secret') }}</label><input type="password" id="app_secret" name="app_secret" autocomplete="off" placeholder="{{ $c->wa('app_secret') ? __('Saved. Leave empty to keep.') : '' }}"></div>
    <div class="field"><label for="api_version">{{ __('Graph API version') }}</label><input id="api_version" name="api_version" value="{{ $c->wa('api_version', 'v23.0') }}" required pattern="v[0-9]{2}\.[0-9]"></div>
    <div><button class="btn p">{{ __('Save') }}</button></div>
  </form>
  <section class="card form">
    <h2>{{ __('Webhook (enter these in Meta)') }}</h2>
    <dl class="dl"><dt>{{ __('Callback URL') }}</dt><dd class="mono">{{ $hook }}</dd><dt>{{ __('Verify token') }}</dt><dd class="mono">{{ $c->wa('verify_token') }}</dd><dt>{{ __('Subscribe to') }}</dt><dd class="mono">messages</dd></dl>
    <p class="small muted">{{ __('Only this address is reachable from the internet. Every message must carry Meta\'s signature, made with your app secret; anything else is refused.') }}</p>
  </section>
</div>
<section class="card flush">
  <div class="ch"><h2>{{ __('Numbers') }}</h2></div>
  <div class="numgrid">
    <div class="numhead" aria-hidden="true"><span>{{ __('Label') }}</span><span>{{ __('Phone') }}</span><span>{{ __('Phone number ID') }}</span><span>{{ __('Belongs to') }}</span><span>{{ __('Active') }}</span><span></span></div>
    @foreach($numbers as $n)
      <form method="post" action="{{ route('whatsapp.numbers.update', $n) }}" class="numrow">@csrf @method('put')
        <input class="inline-input" name="label" value="{{ $n->label }}" required aria-label="{{ __('Label') }}">
        <input class="inline-input" name="display_phone" value="{{ $n->display_phone }}" required aria-label="{{ __('Phone') }}">
        <input class="inline-input mono" name="phone_number_id" value="{{ $n->phone_number_id }}" required aria-label="{{ __('Phone number ID') }}">
        <select name="user_id" aria-label="{{ __('Belongs to') }}"><option value="">{{ __('Company (shared)') }}</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected($n->user_id === $p->id)>{{ $p->name }}</option>@endforeach</select>
        <label class="check"><input type="checkbox" name="active" value="1" @checked($n->active)><span class="nh">{{ __('Active') }}</span></label>
        <button class="btn sm">{{ __('Save') }}</button>
      </form>
    @endforeach
    <form method="post" action="{{ route('whatsapp.numbers.store') }}" class="numrow new">@csrf
      <input class="inline-input" name="label" required placeholder="{{ __('Label, e.g. Amit WhatsApp') }}" aria-label="{{ __('Label') }}">
      <input class="inline-input" name="display_phone" required placeholder="+91 98xxx xxxxx" aria-label="{{ __('Phone') }}">
      <input class="inline-input mono" name="phone_number_id" required placeholder="{{ __('Phone number ID') }}" aria-label="{{ __('Phone number ID') }}">
      <select name="user_id" aria-label="{{ __('Belongs to') }}"><option value="">{{ __('Company (shared)') }}</option>@foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
      <input type="hidden" name="active" value="1"><span></span>
      <button class="btn sm p"><x-icon name="plus"/>{{ __('Add number') }}</button>
    </form>
  </div>
</section>
<form method="post" action="{{ route('whatsapp.translation') }}" class="card form" id="translation">
  @csrf
  <h2>{{ __('Translation: talk to every client in their own language') }}</h2>
  <p class="small muted">{{ __('Client messages are shown with their meaning in each salesperson\'s language. A salesperson writes in English, Hindi or Hinglish; the client gets it in their language, after a meaning check. Phone numbers, emails and links are never sent to the translation service.') }}</p>
  @error('translation')<p class="flash bad">{{ $message }}</p>@enderror
  <div class="f3">
    <div class="field"><label for="provider">{{ __('Translation service') }}</label><select id="provider" name="provider">@foreach(\App\Services\Translation\Translator::PROVIDERS as $k => $l)<option value="{{ $k }}" @selected($c->tr('provider', 'off') === $k)>{{ __($l) }}</option>@endforeach</select></div>
    <label class="check"><input type="checkbox" name="auto" value="1" @checked($c->tr('auto', true))> {{ __('Translate client messages automatically') }}</label>
  </div>
  <h3>Bhashini <span class="small muted">{{ __('(free; register at bhashini.gov.in → My Profile)') }}</span></h3>
  <div class="f3">
    <div class="field"><label for="bhashini_user">{{ __('User ID') }}</label><input id="bhashini_user" name="bhashini_user" value="{{ $c->tr('bhashini_user') }}" autocomplete="off"></div>
    <div class="field"><label for="bhashini_key">{{ __('ULCA API key') }}</label><input type="password" id="bhashini_key" name="bhashini_key" autocomplete="off" placeholder="{{ $c->tr('bhashini_key') ? __('Saved. Leave empty to keep.') : '' }}"></div>
    <div class="field"><label for="bhashini_pipeline">{{ __('Pipeline ID (leave as is)') }}</label><input id="bhashini_pipeline" name="bhashini_pipeline" value="{{ $c->tr('bhashini_pipeline', \App\Services\Translation\BhashiniProvider::DEFAULT_PIPELINE) }}"></div>
  </div>
  <h3>Google / Microsoft <span class="small muted">{{ __('(the company\'s own key and bill)') }}</span></h3>
  <div class="f3">
    <div class="field"><label for="google_key">{{ __('Google Cloud API key') }}</label><input type="password" id="google_key" name="google_key" autocomplete="off" placeholder="{{ $c->tr('google_key') ? __('Saved. Leave empty to keep.') : '' }}"></div>
    <div class="field"><label for="ms_key">{{ __('Microsoft Translator key') }}</label><input type="password" id="ms_key" name="ms_key" autocomplete="off" placeholder="{{ $c->tr('ms_key') ? __('Saved. Leave empty to keep.') : '' }}"></div>
    <div class="field"><label for="ms_region">{{ __('Microsoft region (e.g. centralindia)') }}</label><input id="ms_region" name="ms_region" value="{{ $c->tr('ms_region') }}"></div>
  </div>
  <div class="row">
    <button class="btn p">{{ __('Save') }}</button>
    <label class="sr" for="tlang">{{ __('Test language') }}</label>
    <select id="tlang" name="lang" class="sm">@foreach(\App\Services\WhatsApp\LanguageDetector::NAMES as $code => [$en, $native])@if($code !== 'en')<option value="{{ $code }}" @selected($code === 'ta')>{{ $native }} ({{ $en }})</option>@endif @endforeach</select>
    <button class="btn" formaction="{{ route('whatsapp.translation.test') }}"><x-icon name="globe"/>{{ __('Test') }}</button>
  </div>
</form>
@endsection
