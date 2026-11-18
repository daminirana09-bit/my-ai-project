@extends('layouts.app')
@section('title', __('Auto WhatsApp follow-ups'))
@section('content')
@php $T = \App\Models\FollowupRule::TRIGGERS; $V = \App\Models\Campaign::VARIABLES; @endphp
<div class="head"><div><h1>{{ __('Auto WhatsApp follow-ups') }}</h1><p>{{ __('The CRM follows up by itself: when a follow-up date passes, a quotation gets no reply, a lead goes quiet, a customer is due to reorder, or a new lead is not contacted. Checked every 15 minutes during office hours on working days; never to clients who replied STOP; within the WhatsApp daily limit.') }}</p></div>
  <form method="post" action="{{ route('followups.run') }}">@csrf<button class="btn" @disabled(! $open)>{{ __('Run now') }}</button></form></div>
@if(! $open)<p class="flash warn">{{ __('Outside office hours now: follow-ups wait until the office opens.') }}</p>@endif
@if($remaining === 0)<p class="flash warn">{{ __('WhatsApp\'s 24-hour limit is used up; follow-ups continue when it frees up.') }}</p>@endif

<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Rule') }}</th><th>{{ __('When') }}</th><th>{{ __('Message') }}</th><th class="r">{{ __('Due now') }}</th><th class="r">{{ __('Sent (30 days)') }}</th><th class="r">{{ __('Replied') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse($rules as $r)
      <tr><td><b>{{ $r->name }}</b></td><td>{{ __($T[$r->trigger][0]) }}<span class="sub">{{ $r->after }} {{ __($T[$r->trigger][1]) }} · {{ __('again after :d days', ['d' => $r->repeat_days]) }}</span></td>
        <td class="small">@if($r->template_name)<span class="mono">{{ $r->template_name }}</span> ({{ $r->template_language }})@endif @if($r->text)<span class="sub">{{ \Illuminate\Support\Str::limit($r->text, 80) }}</span>@endif</td>
        <td class="r num">{{ $waiting[$r->id] }}</td><td class="r num">{{ $r->sent30 }}@if($r->failed30)<span class="sub bad-t">{{ __(':n failed', ['n' => $r->failed30]) }}</span>@endif</td><td class="r num">{{ $replies[$r->id] }}</td>
        <td><span class="pill {{ $r->active ? 'good' : '' }}">{{ $r->active ? __('On') : __('Off') }}</span></td></tr>
    @empty
      <tr><td colspan="7" class="empty">{{ __('No rules yet. Add the first one below.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

@foreach($rules->push(new \App\Models\FollowupRule(['trigger' => 'follow_up_due', 'after' => 0, 'repeat_days' => 7, 'template_language' => 'en', 'active' => true])) as $r)
  <details class="card form fu-rule" @if(! $r->exists) open id="new-rule" @endif>
    <summary><b>{{ $r->exists ? $r->name : __('Add a follow-up rule') }}</b></summary>
    <form method="post" action="{{ $r->exists ? route('followups.update', $r) : route('followups.store') }}" class="form">
      @csrf @if($r->exists) @method('put') @endif
      @php $p = $r->exists ? 'r'.$r->id.'_' : 'n_'; @endphp
      <div class="f3">
        <div class="field"><label for="{{ $p }}name">{{ __('Name') }}</label><input id="{{ $p }}name" name="name" maxlength="100" required value="{{ $r->name }}" placeholder="{{ __('e.g. Quotation reminder') }}"></div>
        <div class="field"><label for="{{ $p }}trigger">{{ __('Send when') }}</label><select id="{{ $p }}trigger" name="trigger">@foreach($T as $k => [$l, $u])<option value="{{ $k }}" @selected($r->trigger === $k)>{{ __($l) }}</option>@endforeach</select></div>
        <div class="field"><label for="{{ $p }}after">{{ __('After (days; hours for a new lead)') }}</label><input type="number" id="{{ $p }}after" name="after" min="0" max="365" required value="{{ $r->after }}"></div>
        <div class="field"><label for="{{ $p }}repeat">{{ __('Not again to the same client for (days)') }}</label><input type="number" id="{{ $p }}repeat" name="repeat_days" min="1" max="365" required value="{{ $r->repeat_days }}"></div>
        <div class="field"><label for="{{ $p }}tpl">{{ __('Approved template name') }}</label><input id="{{ $p }}tpl" name="template_name" value="{{ $r->template_name }}" pattern="[a-z0-9_]+" placeholder="quote_followup"></div>
        <div class="field"><label for="{{ $p }}lang">{{ __('Template language code') }}</label><input id="{{ $p }}lang" name="template_language" value="{{ $r->template_language }}" placeholder="en, hi…"></div>
      </div>
      <div class="f3">
        @for($i = 0; $i < 3; $i++)
          <div class="field"><label for="{{ $p }}var{{ $i }}">{{ __('Blank :n', ['n' => $i + 1]) }}</label><select id="{{ $p }}var{{ $i }}" name="var[]"><option value="">—</option>@foreach($V as $k => $l)<option value="{{ $k }}" @selected(($r->variables[$i] ?? '') === $k)>{{ __($l) }}</option>@endforeach</select></div>
        @endfor
      </div>
      <div class="field"><label for="{{ $p }}text">{{ __('Message when the client wrote in the last 24 hours (optional; sent in their language)') }}</label>
        <textarea id="{{ $p }}text" name="text" rows="2" maxlength="1000" placeholder="{{ __('Namaste {name}, did you get a chance to look at our quotation? {rep}, {company}') }}">{{ $r->text }}</textarea>
        <span class="small muted">{{ __('{name}, {firm}, {city}, {rep}, {company} are filled in. Outside 24 hours WhatsApp allows only the approved template.') }}</span></div>
      <div class="row">@if($r->exists)<label class="check"><input type="checkbox" name="active" value="1" @checked($r->active)> {{ __('On') }}</label>@endif
        <button class="btn p">{{ $r->exists ? __('Save') : __('Add rule') }}</button>
        @if($r->exists)<button class="btn bad" formaction="{{ route('followups.destroy', $r) }}" name="_method" value="delete" data-confirm="{{ __('Delete this rule?') }}"><x-icon name="trash"/></button>@endif</div>
    </form>
  </details>
@endforeach

<section class="card flush">
  <div class="ch"><h2>{{ __('Latest follow-ups') }}</h2></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('When') }}</th><th>{{ __('Client') }}</th><th>{{ __('Rule') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse($log as $l)
      <tr><td class="small">{{ $l->sent_at->format('d M, h:i A') }}</td><td>@if($l->lead)<a href="{{ route('leads.show', $l->lead_id) }}">{{ $l->lead->firm_name }}</a><span class="sub">{{ $l->lead->city }}</span>@endif</td>
        <td>{{ $l->rule?->name }}</td><td><span class="pill {{ $l->status === 'sent' ? 'good' : ($l->status === 'failed' ? 'bad' : '') }}">{{ __(ucfirst($l->status)) }}</span>@if($l->error)<span class="sub">{{ $l->error }}</span>@endif</td></tr>
    @empty
      <tr><td colspan="4" class="empty">{{ __('Nothing sent yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
