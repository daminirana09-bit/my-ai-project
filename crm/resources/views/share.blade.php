@extends('layouts.app')
@section('title', __('Share & reviews'))
@section('content')
@php $me = auth()->user(); $links = collect(\App\Models\CompanySetting::LINKS)->except("website")->filter(fn ($m, $k) => $c->link($k)); @endphp
<div class="head"><div><h1>{{ __('Share & reviews') }}</h1><p>{{ __('Your company\'s website, contact details and social pages, ready to share with every client. More followers and Google reviews, one click each.') }}</p></div></div>

<div class="grid g2">
  <section class="card form">
    <h2>{{ $c->company_name }}</h2>
    <dl class="dl">
      @if($c->link('website'))<dt>{{ __('Website') }}</dt><dd><a href="{{ $c->link('website') }}" target="_blank" rel="noopener noreferrer">{{ $c->link('website') }}</a></dd>@endif
      @if($c->phone)<dt>{{ __('Phone') }}</dt><dd>{{ $c->phone }}</dd>@endif
      @if($c->email)<dt>{{ __('Email') }}</dt><dd>{{ $c->email }}</dd>@endif
      @if($c->address)<dt>{{ __('Address') }}</dt><dd>{{ $c->address }}</dd>@endif
      @if($c->gstin)<dt>GSTIN</dt><dd class="mono">{{ $c->gstin }}</dd>@endif
    </dl>
    @if($links->isEmpty())<p class="note">{{ $me->isAdmin() ? __('Add your website, social pages and Google review link below.') : __('Your admin has not added the company links yet.') }}</p>@endif
    <div class="links">
      @foreach($links as $k => [$label])
        <div class="linkrow"><span class="lbl">{{ __($label) }}</span><a href="{{ $c->link($k) }}" target="_blank" rel="noopener noreferrer" class="mono">{{ $c->link($k) }}</a>
          <button type="button" class="btn sm" data-copy="{{ $c->link($k) }}">{{ __('Copy') }}</button></div>
      @endforeach
    </div>
  </section>
  <section class="card form">
    <h2>{{ __('How to send to a client') }}</h2>
    <ol class="steps">
      <li>{{ __('Open the client\'s lead.') }}</li>
      <li>{{ __('Under "Share with this client" press "Company profile" or "Ask for Google review".') }}</li>
      <li>{{ __('It goes from your WhatsApp number in the CRM. Outside WhatsApp\'s 24-hour window, use "Open in my WhatsApp": your own WhatsApp opens with the message ready.') }}</li>
    </ol>
    @if($me->allows('send_campaigns'))
      <h3>{{ __('Ask all customers for a review') }}</h3>
      <p class="small muted">{{ __('To message many clients at once WhatsApp needs a template approved by Meta (for example "review_request" with blanks for the name and the review link).') }}</p>
      <div><a class="btn" href="{{ $bulkReview }}"><x-icon name="megaphone"/>{{ __('Review request to all won customers') }}</a></div>
    @endif
  </section>
</div>

<div class="grid g2">
  @foreach($preview as $k => $text)
    <section class="card form">
      <h2>{{ $k === 'review' ? __('Google review request') : __('Company profile message') }}</h2>
      <pre class="msg">{{ $text }}</pre>
      <div><button type="button" class="btn sm" data-copy="{{ $text }}">{{ __('Copy message') }}</button></div>
    </section>
  @endforeach
</div>

@if($me->isAdmin())
<form method="post" action="{{ route('share.update') }}" class="card form">
  @csrf
  <h2>{{ __('Company links (admin)') }}</h2>
  <div class="f3">
    <div class="field"><label for="website_phone">{{ __('Phone') }}</label><input id="website_phone" name="website_phone" value="{{ old('website_phone', $c->phone) }}" maxlength="30"></div>
    <div class="field"><label for="website_email">{{ __('Email') }}</label><input type="email" id="website_email" name="website_email" value="{{ old('website_email', $c->email) }}" maxlength="150"></div>
    @foreach(\App\Models\CompanySetting::LINKS as $k => [$label, $example])
      <div class="field"><label for="link_{{ $k }}">{{ __($label) }}</label><input type="url" id="link_{{ $k }}" name="link_{{ $k }}" value="{{ old('link_'.$k, $c->link($k)) }}" placeholder="{{ $example }}" maxlength="300">@error('link_'.$k)<small class="err">{{ $message }}</small>@enderror</div>
    @endforeach
  </div>
  <p class="small muted">{{ __('Google review link: in your Google Business Profile choose "Ask for reviews" and copy the link.') }}</p>
  <h3>{{ __('Ready messages') }}</h3>
  <p class="small muted">{{ __('{name} = client, {company}, {rep} = salesperson, {website}, {phone}, {email}, {links} = all social pages, {review} = Google review link. A line whose detail is empty is left out.') }}</p>
  <div class="f2">
    <div class="field"><label for="msg_profile">{{ __('Company profile message') }}</label><textarea id="msg_profile" name="msg_profile" rows="9" maxlength="1500">{{ old('msg_profile', $c->shareMessage('profile')) }}</textarea></div>
    <div class="field"><label for="msg_review">{{ __('Google review request') }}</label><textarea id="msg_review" name="msg_review" rows="9" maxlength="1500">{{ old('msg_review', $c->shareMessage('review')) }}</textarea></div>
  </div>
  <div><button class="btn p">{{ __('Save') }}</button></div>
</form>
@endif
@endsection
