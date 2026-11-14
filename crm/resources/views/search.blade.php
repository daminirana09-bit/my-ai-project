@extends('layouts.app')
@section('title', __('Search'))
@section('content')
@php $me = auth()->user(); @endphp
<div class="head"><div><h1>{{ __('Search') }}</h1><p>{{ $q !== '' ? __('Results for ":q"', ['q' => $q]) : __('Type at least 2 letters in the search box above.') }} {{ __('Phone and email are matched in full.') }}</p></div></div>
@if($q !== '')
<section class="card flush">
  <div class="ch"><h2>{{ __('Leads') }} ({{ $leads->count() }})</h2></div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Firm') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Status') }}</th>@if($me->canManage())<th>{{ __('Salesperson') }}</th>@endif</tr></thead>
    <tbody>
    @forelse($leads as $l)
      <tr><td><a href="{{ route('leads.show', $l) }}"><b>{{ $l->firm_name }}</b></a><span class="sub">{{ $l->city }}</span></td><td>{{ $l->contact_name ?: '—' }}<span class="sub mono">{{ $l->maskedPhone() }}</span></td>
        <td><span class="pill {{ $l->status }}">{{ __(\App\Models\Lead::STATUSES[$l->status]) }}</span></td>@if($me->canManage())<td>{{ $l->owner?->name ?? __('Unassigned') }}</td>@endif</tr>
    @empty
      <tr><td colspan="4" class="empty">{{ __('No leads found.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<div class="grid g2 stretch">
  <section class="card"><h2>{{ __('Products') }} ({{ $products->count() }})</h2>
    <div class="list">@forelse($products as $p)<div class="item"><span class="bar"></span><div><b>{{ $p->name }}</b><small>{{ $p->composition }} · {{ $p->packing }}</small></div><span class="num">{{ $p->rate !== null ? '₹'.number_format((float) $p->rate, 2) : '' }}</span></div>@empty<p class="empty">{{ __('No products found.') }}</p>@endforelse</div>
  </section>
  <section class="card"><h2>{{ __('Tasks') }} ({{ $tasks->count() }})</h2>
    <div class="list">@forelse($tasks as $t)<a class="item" href="{{ route('tasks.index', ['view' => 'team', 'focus' => $t->id]) }}"><span class="bar warn"></span><div><b>{{ $t->title }}</b><small>{{ $t->assignee?->name }}</small></div><span class="pill {{ $t->status }}">{{ __(\App\Models\Task::STATUSES[$t->status]) }}</span></a>@empty<p class="empty">{{ __('No tasks found.') }}</p>@endforelse</div>
  </section>
</div>
@if($users->isNotEmpty())
<section class="card"><h2>{{ __('People') }}</h2><div class="list">@foreach($users as $u)<div class="item"><span class="bar info"></span><div><b>{{ $u->name }}</b><small>{{ $u->email }} · {{ __(\App\Models\User::ROLES[$u->role]) }}</small></div><span></span></div>@endforeach</div></section>
@endif
@endif
@endsection
