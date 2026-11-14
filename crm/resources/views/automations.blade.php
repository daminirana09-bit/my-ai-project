@extends('layouts.app')
@section('title', __('Automations'))
@section('content')
@php $pool = collect($c->automations['assign_pool'] ?? []); @endphp
<div class="head"><div><h1>{{ __('Automations') }}</h1><p>{{ __('Work the CRM does by itself. Each one can be switched off.') }}</p></div></div>
<form method="post" action="{{ route('automations.update') }}" class="grid g2 stretch">
  @csrf
  <section class="card form">
    <h2>{{ __('WhatsApp') }}</h2>
    @foreach(['away_auto_reply' => ['Auto-reply when the salesperson is away', 'On half day, leave or meeting, clients get a reply in their language and wait for a call.'],
              'absent_auto_reply' => ['Auto-reply when the salesperson is not at work', 'During office hours: not punched in an hour after start time, or punched out and gone (lunch is not counted).'],
              'after_hours_auto_reply' => ['Auto-reply outside office hours', 'After office time (for example after 6 PM), before it starts and on weekly off. Each person\'s own timing is used.'],
              'urgent_alert' => ['Alert on urgent messages', 'URGENT or complaint messages alert the backup person and managers.']] as $k => [$t, $d])
      <label class="check top"><input type="checkbox" name="{{ $k }}" value="1" @checked($c->automation($k))><span><b>{{ __($t) }}</b><span class="sub muted small">{{ __($d) }}</span></span></label>
    @endforeach
  </section>
  <section class="card form">
    <h2>{{ __('Leads and follow-ups') }}</h2>
    @foreach(['auto_assign_new_leads' => ['Give new leads out automatically', 'Leads from the company WhatsApp number or the website form go to the rep with the fewest new leads today.'],
              'stale_lead_tasks' => ['Task for leads not updated in 30 days', 'Every morning the owner gets a follow-up task.'],
              'overdue_follow_up_alert' => ['Tell managers about late follow-ups', 'Every morning, managers see who has follow-ups more than 2 days late.']] as $k => [$t, $d])
      <label class="check top"><input type="checkbox" name="{{ $k }}" value="1" @checked($c->automation($k))><span><b>{{ __($t) }}</b><span class="sub muted small">{{ __($d) }}</span></span></label>
    @endforeach
    <fieldset class="form"><legend class="small muted">{{ __('Reps who receive new leads (none ticked = all reps)') }}</legend>
      <div class="row">@foreach($reps as $r)<label class="check"><input type="checkbox" name="assign_pool[]" value="{{ $r->id }}" @checked($pool->contains($r->id))> {{ $r->name }}</label>@endforeach</div>
    </fieldset>
  </section>
  <div><button class="btn p">{{ __('Save automations') }}</button></div>
</form>
@endsection
