@extends('layouts.app')
@section('title', __('Tasks'))
@section('content')
@php $me = auth()->user(); $q = request()->query(); @endphp
<div class="head"><div><h1>{{ __('Tasks') }}</h1><p>{{ __('To-dos for you and your team: call back, send samples, collect payment. Due tasks also appear in Reminders on the dashboard.') }}</p></div></div>

<div class="grid g21">
  <div class="grid">
    @if($me->canManage())
    <nav class="tabs" aria-label="{{ __('Tasks') }}">
      <a class="{{ $view === 'mine' ? 'on' : '' }}" href="{{ route('tasks.index', ['view' => 'mine']) }}">{{ __('My tasks') }}</a>
      <a class="{{ $view === 'team' ? 'on' : '' }}" href="{{ route('tasks.index', ['view' => 'team']) }}">{{ __('Team tasks') }}</a>
      <a class="{{ $view === 'given' ? 'on' : '' }}" href="{{ route('tasks.index', ['view' => 'given']) }}">{{ __('Given by me') }}</a>
    </nav>
    @endif
    <div class="chips">
      <a class="chip {{ ! $status ? 'on' : '' }}" href="{{ route('tasks.index', array_merge($q, ['status' => null, 'page' => null])) }}">{{ __('All') }}</a>
      @foreach(\App\Models\Task::STATUSES as $k => $l)<a class="chip {{ $status === $k ? 'on' : '' }}" href="{{ route('tasks.index', array_merge($q, ['status' => $k, 'page' => null])) }}">{{ __($l) }}</a>@endforeach
    </div>
    <section class="card flush">
      <div class="tbl"><table>
        <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Due') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($tasks as $t)
          <tr class="{{ $focus === $t->id ? 'focus' : '' }}">
            <td><b>{{ $t->title }}</b>@if($t->lead)<span class="sub"><a href="{{ route('leads.show', $t->lead_id) }}">{{ $t->lead->firm_name }}</a></span>@endif @if($t->details)<span class="sub">{{ $t->details }}</span>@endif</td>
            <td class="nowrap">@if($t->due_on)<span class="pill {{ $t->isOverdue() ? 'bad' : ($t->due_on->isToday() ? 'warn' : '') }}">{{ $t->due_on->isToday() ? __('today') : $t->due_on->format('d M') }}</span>@else — @endif</td>
            <td><span class="pill {{ $t->priority }}">{{ __(\App\Models\Task::PRIORITIES[$t->priority]) }}</span></td>
            <td>{{ $t->assignee?->name }}@if($t->created_by && $t->created_by !== $t->assigned_to)<span class="sub">{{ __('from :name', ['name' => $t->creator?->name]) }}</span>@endif</td>
            <td><form method="post" action="{{ route('tasks.status', $t) }}">@csrf @method('patch')
              <label class="sr" for="ts{{ $t->id }}">{{ __('Status') }}</label>
              <select id="ts{{ $t->id }}" name="status" data-autosubmit>@foreach(\App\Models\Task::STATUSES as $k => $l)<option value="{{ $k }}" @selected($t->status === $k)>{{ __($l) }}</option>@endforeach</select></form></td>
            <td class="r">@if($me->isAdmin() || $t->created_by === $me->id)<form method="post" action="{{ route('tasks.destroy', $t) }}">@csrf @method('delete')<button class="btn sm" data-confirm="{{ __('Delete this task?') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash"/></button></form>@endif</td>
          </tr>
        @empty
          <tr><td colspan="6" class="empty">{{ __('No tasks here.') }}</td></tr>
        @endforelse
        </tbody>
      </table></div>
    </section>
    @include('partials.pager', ['p' => $tasks])
  </div>

  <form method="post" action="{{ route('tasks.store') }}" class="card form">
    @csrf
    <h2>{{ __('New task') }}</h2>
    <div class="field"><label for="title">{{ __('What needs to be done?') }}</label><input id="title" name="title" required maxlength="200"></div>
    <div class="field"><label for="details">{{ __('Details') }}</label><textarea id="details" name="details"></textarea></div>
    <div class="f2">
      <div class="field"><label for="due_on">{{ __('Due date') }}</label><input type="date" id="due_on" name="due_on" value="{{ today()->format('Y-m-d') }}"></div>
      <div class="field"><label for="priority">{{ __('Priority') }}</label><select id="priority" name="priority">@foreach(\App\Models\Task::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($k === 'normal')>{{ __($l) }}</option>@endforeach</select></div>
    </div>
    @if($people->count() > 1)
      <div class="field"><label for="assigned_to">{{ __('Assign to') }}</label><select id="assigned_to" name="assigned_to">@foreach($people as $p)<option value="{{ $p->id }}" @selected($p->id === $me->id)>{{ $p->name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label for="t_lead">{{ __('Client (optional)') }}</label><select id="t_lead" name="lead_id"><option value="">—</option>@foreach($leads as $l)<option value="{{ $l->id }}">{{ $l->firm_name }}</option>@endforeach</select></div>
    <div><button class="btn p"><x-icon name="plus"/>{{ __('Add task') }}</button></div>
  </form>
</div>
@endsection
