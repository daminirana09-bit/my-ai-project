@extends('layouts.app')
@section('title', __('WhatsApp settings'))
@section('content')
@php $hook = url('/webhook/whatsapp'); $form = url('/webhook/lead/'.$c->lead_capture_token); @endphp
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
<section class="card form">
  <h2>{{ __('Website enquiry form') }}</h2>
  <p class="small muted">{{ __('Point your website\'s contact form here. New enquiries become leads and are given to a salesperson automatically. Fields: name, phone, email, firm, city, message.') }}</p>
  <p class="secret">{{ $form }}</p>
  <form method="post" action="{{ route('whatsapp.token') }}">@csrf<button class="btn sm" data-confirm="{{ __('The old link will stop working. Continue?') }}">{{ __('Create a new link') }}</button></form>
</section>
@endsection
