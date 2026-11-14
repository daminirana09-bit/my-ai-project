@extends('layouts.app')
@section('title', $lead->firm_name)
@section('content')
@php $me = auth()->user(); @endphp
<div class="head">
  <div><h1>{{ $lead->firm_name }}</h1><p>{{ implode(' · ', array_filter([$lead->contact_name, implode(', ', array_filter([$lead->city, $lead->state]))])) }}@if($lead->contact_name || $lead->city || $lead->state) · @endif<span class="pill {{ $lead->status }}">{{ __(\App\Models\Lead::STATUSES[$lead->status]) }}</span>@if($lead->temperature) <span class="pill {{ $lead->temperature }}">{{ __(\App\Models\Lead::TEMPERATURES[$lead->temperature]) }}</span>@endif</p></div>
  <div class="row"><a class="btn" href="{{ route('quotes.create', ['lead' => $lead->id]) }}"><x-icon name="plus"/>{{ __('New quote') }}</a><a class="btn" href="{{ route('leads.edit', $lead) }}"><x-icon name="edit"/>{{ __('Edit') }}</a></div>
</div>

@if($lead->clashes->isNotEmpty() && $me->canManage())
  <div class="flash warn">{{ __('Clash: :names also tried to work this client.', ['names' => $lead->clashes->pluck('otherUser.name')->filter()->join(', ')]) }} <a href="{{ route('clashes.index') }}">{{ __('Resolve') }}</a></div>
@endif

<div class="grid g21">
  <div class="grid">
    <form method="post" action="{{ route('leads.activity', $lead) }}" class="card form">
      @csrf
      <h2>{{ __('Log a call or update') }}</h2>
      <div class="f3">
        <div class="field"><label for="type">{{ __('Type') }}</label><select id="type" name="type">@foreach(['call', 'whatsapp', 'visit', 'note'] as $t)<option value="{{ $t }}">{{ __(\App\Models\LeadActivity::TYPES[$t]) }}</option>@endforeach</select></div>
        <div class="field"><label for="outcome">{{ __('Call result') }}</label><select id="outcome" name="outcome"><option value="">—</option>@foreach(\App\Models\LeadActivity::CALL_OUTCOMES as $k => $label)<option value="{{ $k }}">{{ __($label) }}</option>@endforeach</select></div>
        <div class="field"><label for="a_status">{{ __('Change status') }}</label><select id="a_status" name="status"><option value="">{{ __('No change') }}</option>@foreach(\App\Models\Lead::STATUSES as $k => $label)<option value="{{ $k }}">{{ __($label) }}</option>@endforeach</select></div>
      </div>
      <div class="f2">
        <div class="field"><label for="note">{{ __('What happened?') }}</label><input id="note" name="note"></div>
        <div class="field"><label for="a_next">{{ __('Next follow-up') }}</label><input type="date" id="a_next" name="next_follow_up_on"></div>
      </div>
      <div><button class="btn p">{{ __('Save') }}</button></div>
    </form>

    <section class="card">
      <div class="ch"><h2>{{ __('Tasks') }}</h2></div>
      <div class="list">
        @forelse($lead->tasks as $t)
          <a class="item" href="{{ route('tasks.index', ['view' => 'team', 'focus' => $t->id]) }}"><span class="bar {{ $t->isOverdue() ? 'bad' : 'warn' }}"></span><div><b>{{ $t->title }}</b><small>{{ $t->assignee?->name }} · {{ $t->due_on?->format('d M') ?? '—' }}</small></div><span class="pill {{ $t->status }}">{{ __(\App\Models\Task::STATUSES[$t->status]) }}</span></a>
        @empty
          <p class="empty">{{ __('No tasks for this client.') }}</p>
        @endforelse
      </div>
      <form method="post" action="{{ route('tasks.store') }}" class="row">@csrf
        <input type="hidden" name="lead_id" value="{{ $lead->id }}"><input type="hidden" name="priority" value="normal">
        <div class="field"><label for="qt">{{ __('Quick task') }}</label><input id="qt" name="title" required maxlength="200" placeholder="{{ __('e.g. Send sample and rate list') }}"></div>
        <div class="field"><label for="qd">{{ __('Due date') }}</label><input type="date" id="qd" name="due_on" value="{{ today()->addDay()->format('Y-m-d') }}"></div>
        <button class="btn"><x-icon name="plus"/>{{ __('Add task') }}</button>
      </form>
    </section>

    @if($lead->quotes->isNotEmpty() || $lead->conversations->isNotEmpty())
    <section class="card">
      <h2>{{ __('Quotations and WhatsApp') }}</h2>
      <div class="list">
        @foreach($lead->quotes as $q)<a class="item" href="{{ route('quotes.show', $q) }}"><span class="bar"></span><div><b class="mono">{{ $q->number }}</b><small>{{ $q->quote_date->format('d M Y') }} · {{ inr($q->grand_total, false) }}</small></div><span class="pill">{{ __(\App\Models\Quote::STATUSES[$q->status]) }}</span></a>@endforeach
        @foreach($lead->conversations as $c)@if($c->isVisibleTo(auth()->user()))<a class="item" href="{{ route('inbox.show', $c) }}"><span class="bar info"></span><div><b>{{ __('WhatsApp chat') }}</b><small>{{ $c->last_message_at?->diffForHumans() }}</small></div><span></span></a>@endif @endforeach
      </div>
    </section>
    @endif

    @php $shareFiles = \App\Models\ProductFile::orderBy('title')->get(['id', 'title', 'extension']); @endphp
    <section class="card form" id="share">
      <div class="ch"><h2>{{ __('Share with this client') }}</h2>@if($lead->review_requested_at)<span class="pill good">{{ __('Review asked :d', ['d' => $lead->review_requested_at->format('d M')]) }}</span>@endif</div>
      @error('share')<p class="flash bad">{{ $message }}</p>@enderror
      <div class="sharegrid">
        @foreach(['profile' => __('Company profile & links'), 'review' => __('Ask for Google review')] as $k => $label)
          <div class="sharebox"><b>{{ $label }}</b>
            <div class="row">
              <form method="post" action="{{ route('leads.share', [$lead, $k]) }}">@csrf<button class="btn sm p"><x-icon name="chat"/>{{ __('Send on WhatsApp') }}</button></form>
              <form method="post" action="{{ route('leads.share', [$lead, $k]) }}" target="_blank">@csrf<input type="hidden" name="own" value="1"><button class="btn sm">{{ __('Open in my WhatsApp') }}</button></form>
            </div>
          </div>
        @endforeach
      </div>
      @if($shareFiles->isNotEmpty())
        <form method="post" action="{{ route('leads.send-file', $lead) }}" class="row">@csrf
          <div class="field grow"><label for="file_id">{{ __('Price list / catalogue') }}</label><select id="file_id" name="file_id" required>@foreach($shareFiles as $f)<option value="{{ $f->id }}">{{ $f->title }} ({{ strtoupper($f->extension) }})</option>@endforeach</select></div>
          <button class="btn"><x-icon name="chat"/>{{ __('Send file on WhatsApp') }}</button>
        </form>
      @else
        <p class="small muted">{{ __('Upload price lists and catalogues under Catalogue & files to send them from here.') }}</p>
      @endif
    </section>

    <div class="card"><h2>{{ __('History') }}</h2>
      <div class="timeline">
        @forelse($lead->activities as $a)
          <div class="tl {{ $a->type }}"><b>{{ __(\App\Models\LeadActivity::TYPES[$a->type] ?? $a->type) }}</b>@if($a->outcome) · {{ __(\App\Models\LeadActivity::CALL_OUTCOMES[$a->outcome] ?? $a->outcome) }}@endif
            <div>{{ $a->note }}</div><span class="small muted">{{ $a->user?->name }} · {{ $a->occurred_at->format('d M Y, h:i A') }}</span></div>
        @empty
          <p class="muted">{{ __('No activity yet.') }}</p>
        @endforelse
      </div>
    </div>
  </div>

  <div class="grid">
    <div class="card"><h2>{{ __('Details') }}</h2>
      <table><tbody>
        <tr><td class="muted">{{ __('Phone / WhatsApp') }}</td><td class="mono">{{ $lead->phone ?: '—' }}</td></tr>
        <tr><td class="muted">{{ __('Email') }}</td><td>{{ $lead->email ?: '—' }}</td></tr>
        <tr><td class="muted">GSTIN</td><td class="mono">{{ $lead->gstin ?: '—' }}</td></tr>
        <tr><td class="muted">{{ __('Orders') }}</td><td>{{ $lead->sales->where('status', 'confirmed')->count() }} · {{ inr($lead->sales->where('status', 'confirmed')->sum('amount')) }}</td></tr>
        <tr><td class="muted">{{ __('Source and tags') }}</td><td>@forelse($lead->tagList() as $i => $t)<a class="{{ $i === 0 && $lead->source ? \App\Models\Lead::tagClass($t) : 'tag' }}" href="{{ route('leads.index', $i === 0 && $lead->source ? ['source' => $t] : ['tag' => $t]) }}">{{ $t }}</a>@empty — @endforelse</td></tr>
        @if($lead->campaign)<tr><td class="muted">{{ __('Campaign / form') }}</td><td>{{ $lead->campaign }}</td></tr>@endif
        @if($lead->referred_by)<tr><td class="muted">{{ __('Referred by') }}</td><td>{{ $lead->referred_by }}</td></tr>@endif
        <tr><td class="muted">{{ __('Client type') }}</td><td>{{ $lead->client_type ?: '—' }}</td></tr>
        <tr><td class="muted">{{ __('Requirement') }}</td><td>{{ $lead->requirement ?: '—' }}</td></tr>
        <tr><td class="muted">{{ __('Expected value') }}</td><td>{{ $lead->expected_value ? inr($lead->expected_value) : '—' }}</td></tr>
        <tr><td class="muted">{{ __('Next follow-up') }}</td><td>{{ $lead->next_follow_up_on?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td class="muted">{{ __('Last contact') }}</td><td>{{ $lead->last_contact_at?->diffForHumans() ?? '—' }}</td></tr>
        <tr><td class="muted">{{ __('Salesperson') }}</td><td>{{ $lead->owner?->name ?? __('Unassigned') }}</td></tr>
        <tr><td class="muted">{{ __('Added') }}</td><td>{{ $lead->created_at->format('d M Y') }} · {{ match ($lead->created_via) { 'import' => __('Import'), 'integration' => __('Came in automatically'), 'website' => __('Website form'), 'whatsapp' => 'WhatsApp', default => __('Manual') } }}@if($lead->external_ref) <span class="muted small mono">#{{ $lead->external_ref }}</span>@endif</td></tr>
      </tbody></table>
      @if($lead->notes)<p class="note">{{ $lead->notes }}</p>@endif
    </div>
    @if($me->canManage())
      <form method="post" action="{{ route('leads.assign', $lead) }}" class="card form">
        @csrf
        <h2>{{ __('Assign to salesperson') }}</h2>
        <div class="field"><label for="owner_id">{{ __('Salesperson') }}</label><select id="owner_id" name="owner_id">@foreach($owners as $o)<option value="{{ $o->id }}" @selected($lead->owner_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        <div><button class="btn">{{ __('Assign') }}</button></div>
      </form>
    @endif
  </div>
</div>
@endsection
