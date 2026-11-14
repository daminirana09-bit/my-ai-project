@extends('layouts.app')
@section('title', __('Lead integrations'))
@section('content')
@php
  $help = [
    'indiamart' => __('IndiaMART seller panel → Lead Manager → CRM integration: create the CRM key and paste it here. New enquiries, buy-leads and calls are fetched every 10 minutes. For instant leads, also give IndiaMART the receiving link (Push API).'),
    'tradeindia' => __('My TradeIndia → Inquiries → CRM API: copy the User ID, Profile ID and key. New enquiries are fetched every 10 minutes.'),
    'exportersindia' => __('Ask ExportersIndia support for your lead API link and paste it here, with {from} and {to} where the dates go. Or give them the receiving link.'),
    'justdial' => __('Send the receiving link to your JustDial account manager and ask them to push your leads to it.'),
    'facebook' => __('For Facebook and Instagram lead forms. In your Meta app add Webhooks → Page → leadgen with the callback URL and verify token below. Enter the Page ID, a Page access token with leads_retrieval permission and the app secret, save, then press Connect Page.'),
    'google_ads' => __('In Google Ads open the lead form asset → Lead delivery → Webhook. Paste the URL and key below and press "Send test data".'),
    'other_portal' => __('For any other portal that gives an API link returning a list of enquiries. Put {from} and {to} where the dates go.'),
    'custom' => __('For Zapier, Pabbly Connect, LinkedIn lead forms (through Zapier), IVR and missed-call systems: send name, phone, email, firm, city, message to the receiving link. Optional: source (e.g. LinkedIn Ads) and tags.'),
  ];
  $form = url('/webhook/lead/'.$c->lead_capture_token);
@endphp
<div class="head"><div><h1>{{ __('Lead integrations') }}</h1><p>{{ __('Leads from every platform come in by themselves and are tagged with where they came from. A client who is already in the CRM gets a note, not a second lead.') }}</p></div></div>
@error('fetch')<div class="flash bad">{{ $message }}</div>@enderror
@error('facebook')<div class="flash bad">{{ $message }}</div>@enderror
@error('api_url')<div class="flash bad">{{ $message }}</div>@enderror

<section class="card flush">
  <div class="ch"><h2>{{ __('This month by source') }}</h2><a class="btn sm" href="{{ route('analytics') }}">{{ __('Analytics') }}</a></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Source') }}</th><th class="r">{{ __('Leads') }}</th><th class="r">{{ __('Won') }}</th><th class="r">{{ __('Conversion') }}</th></tr></thead>
    <tbody>
    @forelse($bySource as $s)
      <tr><td>@if($s->src === '—')<span class="muted">{{ __('No source') }}</span>@else<a class="{{ \App\Models\Lead::tagClass($s->src) }}" href="{{ route('leads.index', ['source' => $s->src]) }}">{{ $s->src }}</a>@endif</td><td class="r num">{{ $s->total }}</td><td class="r num">{{ $s->won }}</td><td class="r num">{{ $s->total ? round($s->won * 100 / $s->total) : 0 }}%</td></tr>
    @empty
      <tr><td colspan="4" class="empty">{{ __('No leads this month yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

<div class="grid g2">
@foreach($list as $i)
  @php
    $p = $i->platform;
    $broken = $i->enabled && $i->last_error_at && (! $i->last_success_at || $i->last_error_at->gt($i->last_success_at));
  @endphp
  <section class="card form integ" id="i-{{ $p }}">
    <div class="ch">
      <h2><span class="{{ \App\Models\Lead::tagClass($i->sourceLabel()) }}">{{ __($i->name()) }}</span></h2>
      @if(! $i->enabled)<span class="pill">{{ __('Off') }}</span>@elseif($broken)<span class="pill bad">{{ __('Needs attention') }}</span>@else<span class="pill good">{{ __('On') }}</span>@endif
    </div>
    <p class="small muted">{{ $help[$p] ?? '' }}</p>
    <p class="small stats">{{ __('Today') }} <b>{{ (int) ($today[$p] ?? 0) }}</b> · {{ __('This month') }} <b>{{ (int) ($counts[$p] ?? 0) }}</b> · {{ __('Last lead') }} <b>{{ $i->last_lead_at?->diffForHumans() ?? '—' }}</b>@if($i->info('pull') && $i->last_pulled_at) · {{ __('Last fetched') }} <b>{{ $i->last_pulled_at->diffForHumans() }}</b>@endif</p>
    @if($broken)<p class="note bad small">{{ $i->last_error }} <span class="muted">({{ $i->last_error_at->diffForHumans() }})</span></p>@endif

    <form method="post" action="{{ route('integrations.update', $p) }}" class="form">
      @csrf
      <label class="check"><input type="checkbox" name="enabled" value="1" @checked($i->enabled)> {{ __('Switched on') }}</label>
      @foreach($i->info('fields', []) as $key => [$label, $secret])
        <div class="field"><label for="{{ $p }}-{{ $key }}">{{ __($label) }}</label>
          @if($secret)
            <input type="password" id="{{ $p }}-{{ $key }}" name="{{ $key }}" autocomplete="off" placeholder="{{ $i->conf($key) ? __('Saved. Leave empty to keep.') : '' }}">
          @else
            <input id="{{ $p }}-{{ $key }}" name="{{ $key }}" value="{{ $i->conf($key) }}" autocomplete="off">
          @endif
        </div>
      @endforeach
      <div class="field"><label for="{{ $p }}-tags">{{ __('Extra tags on every lead (comma separated)') }}</label><input id="{{ $p }}-tags" name="extra_tags" value="{{ $i->extra_tags }}" placeholder="{{ __('e.g. PCD, North zone') }}"></div>
      @if($people->isNotEmpty())
        <details class="who">
          <summary>{{ __('Who gets these leads') }}: <b>{{ $i->assign_to ? $people->whereIn('id', $i->assign_to)->pluck('name')->implode(', ') : __('Automatic (Automations)') }}</b></summary>
          <div class="checks">@foreach($people as $u)<label class="check"><input type="checkbox" name="assign_to[]" value="{{ $u->id }}" @checked(in_array($u->id, $i->assign_to ?? [], true))> {{ $u->name }}</label>@endforeach</div>
          <p class="small muted">{{ __('None ticked: the Automations setting decides. Ticked: shared among these people only, fewest new leads first.') }}</p>
        </details>
      @endif
      <div class="row"><button class="btn p">{{ __('Save') }}</button></div>
    </form>

    @if($p === 'facebook')
      <dl class="dl"><dt>{{ __('Callback URL') }}</dt><dd class="mono">{{ $i->receiveUrl() }}</dd><dt>{{ __('Verify token') }}</dt><dd class="mono">{{ $i->conf('verify_token') }}</dd><dt>{{ __('Subscribe to') }}</dt><dd class="mono">leadgen</dd></dl>
    @elseif($p === 'google_ads')
      <dl class="dl"><dt>{{ __('Webhook URL') }}</dt><dd class="mono">{{ $i->receiveUrl() }}</dd><dt>{{ __('Key') }}</dt><dd class="mono">{{ $i->conf('google_key') }}</dd></dl>
    @elseif(! in_array($p, ['other_portal'], true))
      <div class="field"><span class="small muted">{{ __('Receiving link') }}</span><p class="secret sm">{{ $i->receiveUrl() }}</p></div>
    @endif
    @if($i->last_fields)<details class="small muted"><summary>{{ __('Fields received last time') }}</summary><p class="mono">{{ $i->last_fields }}</p></details>@endif

    <div class="row">
      @if($i->info('pull'))<form method="post" action="{{ route('integrations.fetch', $p) }}">@csrf<button class="btn sm" @disabled(! $i->enabled || ! $i->canPull())><x-icon name="download"/>{{ __('Fetch now') }}</button></form>@endif
      @if($p === 'facebook')<form method="post" action="{{ route('integrations.facebook') }}">@csrf<button class="btn sm" @disabled(! $i->conf('page_token'))>{{ __('Connect Page') }}</button></form>@endif
      @if($p !== 'other_portal')<form method="post" action="{{ route('integrations.rotate', $p) }}">@csrf<button class="btn sm" data-confirm="{{ __('The old link will stop working. Continue?') }}">{{ __('Create a new link') }}</button></form>@endif
    </div>
  </section>
@endforeach

  <section class="card form integ" id="i-website">
    <div class="ch"><h2><span class="{{ \App\Models\Lead::tagClass('Website') }}">{{ __('Website enquiry form') }}</span></h2><span class="pill good">{{ __('On') }}</span></div>
    <p class="small muted">{{ __('Point your website\'s contact form here. New enquiries become leads and are given to a salesperson automatically. Fields: name, phone, email, firm, city, message.') }}</p>
    <p class="small muted">{{ __('With the snippet below, leads from Google Ads, Google search, Facebook and Instagram ads are tagged correctly.') }}</p>
    <p class="secret sm">{{ $form }}</p>
    <details><summary class="small">{{ __('Form code for your website') }}</summary>
<pre class="code">&lt;form method="post" action="{{ $form }}"&gt;
  &lt;input name="name" required placeholder="Name"&gt;
  &lt;input name="phone" required placeholder="Mobile"&gt;
  &lt;input name="email" placeholder="Email"&gt;
  &lt;input name="firm" placeholder="Firm"&gt;
  &lt;input name="city" placeholder="City"&gt;
  &lt;textarea name="message" placeholder="Requirement"&gt;&lt;/textarea&gt;
  &lt;input type="hidden" name="utm_source"&gt;&lt;input type="hidden" name="utm_medium"&gt;&lt;input type="hidden" name="utm_campaign"&gt;
  &lt;input type="hidden" name="gclid"&gt;&lt;input type="hidden" name="fbclid"&gt;&lt;input type="hidden" name="referrer"&gt;
  &lt;input name="website" tabindex="-1" autocomplete="off" style="display:none"&gt;
  &lt;input type="hidden" name="redirect" value="https://YOUR-WEBSITE/thank-you"&gt;
  &lt;button&gt;Send&lt;/button&gt;
&lt;/form&gt;
&lt;script&gt;(function(f){var q=new URLSearchParams(location.search);
['utm_source','utm_medium','utm_campaign','gclid','fbclid'].forEach(function(k){
 var v=q.get(k)||sessionStorage.getItem(k);if(v){sessionStorage.setItem(k,v);f.elements[k].value=v;}});
 f.elements.referrer.value=sessionStorage.getItem('ref')||document.referrer;
 if(!sessionStorage.getItem('ref'))sessionStorage.setItem('ref',document.referrer);
})(document.currentScript.previousElementSibling);&lt;/script&gt;</pre>
    </details>
    <form method="post" action="{{ route('whatsapp.token') }}">@csrf<button class="btn sm" data-confirm="{{ __('The old link will stop working. Continue?') }}">{{ __('Create a new link') }}</button></form>
  </section>

  <section class="card form integ">
    <div class="ch"><h2>{{ __('Reference, walk-in and other sources') }}</h2></div>
    <p class="small muted">{{ __('Leads by reference, walk-in, exhibition, cold call or Google Maps are added by your team: choose the source on the lead, and for a reference write who referred the client. Tags can be added to any lead.') }}</p>
    <p class="small">@foreach(\App\Models\MasterOption::values('source') as $s)<span class="{{ \App\Models\Lead::tagClass($s) }}">{{ $s }}</span> @endforeach</p>
    <div class="row"><a class="btn sm" href="{{ route('masters.index') }}">{{ __('Edit the list of sources') }}</a></div>
  </section>
</div>
@endsection
