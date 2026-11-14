@extends('layouts.app')
@section('title', __('New campaign'))
@section('content')
<div class="head"><div><h1>{{ __('New campaign') }}</h1><p>{{ __('First create and get the template approved in Meta WhatsApp Manager. Then enter its exact name and language here.') }}</p></div></div>
@if($numbers->isEmpty())<p class="flash warn">{{ __('No WhatsApp number is set up yet. The admin adds numbers under WhatsApp settings.') }}</p>@endif
<form method="post" action="{{ route('campaigns.store') }}" class="grid g21">
  @csrf
  <section class="card form">
    <h2>{{ __('Message') }}</h2>
    <div class="f2">
      <div class="field"><label for="name">{{ __('Campaign name') }}</label><input id="name" name="name" value="{{ old('name', request('name')) }}" required maxlength="150" placeholder="{{ __('e.g. Diwali scheme 2026') }}"></div>
      <div class="field"><label for="whatsapp_number_id">{{ __('Send from') }}</label><select id="whatsapp_number_id" name="whatsapp_number_id" required>@foreach($numbers as $n)<option value="{{ $n->id }}" @selected((int) old('whatsapp_number_id', request('whatsapp_number_id')) === $n->id)>{{ $n->label }} ({{ $n->display_phone }})</option>@endforeach</select></div>
      <div class="field"><label for="template_name">{{ __('Approved template name') }}</label><input id="template_name" name="template_name" value="{{ old('template_name', request('template_name')) }}" required pattern="[a-z0-9_]+" placeholder="diwali_scheme_2026"></div>
      <div class="field"><label for="template_language">{{ __('Template language code') }}</label><input id="template_language" name="template_language" value="{{ old('template_language', request('template_language', 'en')) }}" required placeholder="en, hi, gu, ta…"></div>
    </div>
    <h3>{{ __('Fill the template\'s blanks') }}</h3>
    <div class="f3">
      @for($i = 0; $i < 3; $i++)
        <div class="field"><label for="var{{ $i }}">{{ __('Blank :n', ['n' => $i + 1]) }}</label><select id="var{{ $i }}" name="var[]"><option value="">—</option>@foreach(\App\Models\Campaign::VARIABLES as $k => $l)<option value="{{ $k }}" @selected((old('var', request('var', []))[$i] ?? '') === $k)>{{ __($l) }}</option>@endforeach</select></div>
      @endfor
    </div>
  </section>
  <section class="card form">
    <h2>{{ __('Who gets it') }}</h2>
    <div class="field"><label for="status">{{ __('Lead status') }}</label><select id="status" name="status"><option value="">{{ __('All') }}</option>@foreach(\App\Models\Lead::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($l) }}</option>@endforeach</select></div>
    <div class="field"><label for="temperature">{{ __('Hot / warm / cold') }}</label><select id="temperature" name="temperature"><option value="">{{ __('All') }}</option>@foreach(\App\Models\Lead::TEMPERATURES as $k => $l)<option value="{{ $k }}" @selected(request('temperature') === $k)>{{ __($l) }}</option>@endforeach</select></div>
    <div class="field"><label for="client_type">{{ __('Client type') }}</label><select id="client_type" name="client_type"><option value="">{{ __('All') }}</option>@foreach($types as $t)<option @selected(request('client_type') === $t)>{{ $t }}</option>@endforeach</select></div>
    <div class="field"><label for="source">{{ __('Source') }}</label><select id="source" name="source"><option value="">{{ __('All') }}</option>@foreach($sources as $t)<option @selected(request('source') === $t)>{{ $t }}</option>@endforeach</select></div>
    <div class="field"><label for="owner_id">{{ __('Salesperson') }}</label><select id="owner_id" name="owner_id"><option value="">{{ __('All') }}</option>@foreach($owners as $o)<option value="{{ $o->id }}" @selected((int) request('owner_id') === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div class="field"><label for="no_order_days">{{ __('Customers with no order in (days)') }}</label><input type="number" min="0" max="365" id="no_order_days" name="no_order_days" value="{{ request('no_order_days') }}"></div>
    @if($count !== null)<p class="flash ok">{{ __(':n clients match (with a phone number, not opted out).', ['n' => $count]) }}</p>@endif
    <div class="row"><button class="btn" formmethod="get" formaction="{{ route('campaigns.create') }}" name="preview" value="1">{{ __('Count clients') }}</button>
      <button class="btn p" data-confirm="{{ __('Send this campaign now?') }}" @disabled($numbers->isEmpty())>{{ __('Send campaign') }}</button></div>
  </section>
</form>
@endsection
