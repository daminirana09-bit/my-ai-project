@extends('layouts.app')
@section('title', __('Leads'))
@section('content')
@php $me = auth()->user(); $q = request()->query(); @endphp
<div class="head">
  <div><h1>{{ __('Leads') }}</h1><p>{{ $me->isRep() ? __('Only your own leads are shown here.') : __('All leads of your team and their status.') }}</p></div>
  <div class="row">
    @if($canExport)
      <a class="btn" href="{{ route('leads.export', array_merge($q, ['format' => 'xlsx'])) }}">{{ __('Export Excel') }}</a>
      <a class="btn" href="{{ route('leads.export', array_merge($q, ['format' => 'csv'])) }}">{{ __('Export CSV') }}</a>
    @endif
    <a class="btn" href="{{ route('leads.import') }}">{{ __('Import') }}</a>
    <a class="btn p" href="{{ route('leads.create') }}">{{ __('New lead') }}</a>
  </div>
</div>

<div class="chips">
  <a class="chip {{ ! request('status') ? 'on' : '' }}" href="{{ route('leads.index', array_merge($q, ['status' => null, 'page' => null])) }}">{{ __('All') }} {{ $counts->sum() }}</a>
  @foreach(\App\Models\Lead::STATUSES as $k => $label)
    <a class="chip {{ request('status') === $k ? 'on' : '' }}" href="{{ route('leads.index', array_merge($q, ['status' => $k, 'page' => null])) }}">{{ __($label) }} {{ $counts[$k] ?? 0 }}</a>
  @endforeach
</div>

<form method="get" class="card row">
  @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
  <div class="field"><label for="q">{{ __('Search firm, contact, city, GSTIN or full phone') }}</label><input id="q" name="q" value="{{ request('q') }}"></div>
  <div class="field"><label for="source">{{ __('Source') }}</label><select id="source" name="source"><option value="">{{ __('All') }}</option>@foreach(\App\Models\Lead::SOURCES as $s)<option @selected(request('source') === $s)>{{ $s }}</option>@endforeach<option @selected(request('source') === 'Import')>Import</option></select></div>
  @if($owners->isNotEmpty())
    <div class="field"><label for="owner">{{ __('Salesperson') }}</label><select id="owner" name="owner"><option value="">{{ __('All') }}</option>@foreach($owners as $o)<option value="{{ $o->id }}" @selected((int) request('owner') === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
  @endif
  <label class="check"><input type="checkbox" name="due" value="1" @checked(request('due'))> {{ __('Follow-up due') }}</label>
  <button class="btn">{{ __('Filter') }}</button>
</form>

<div class="card">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Firm') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Status') }}</th><th>{{ __('Source') }}</th><th class="r">{{ __('Value') }}</th><th>{{ __('Next follow-up') }}</th>@if($me->canManage())<th>{{ __('Salesperson') }}</th>@endif<th>{{ __('Updated') }}</th></tr></thead>
    <tbody>
    @forelse($leads as $l)
      <tr>
        <td><a href="{{ route('leads.show', $l) }}"><b>{{ $l->firm_name }}</b></a><span class="sub">{{ $l->city }}{{ $l->client_type ? ' · '.$l->client_type : '' }}</span></td>
        <td>{{ $l->contact_name }}<span class="sub mono">{{ $l->maskedPhone() }}</span></td>
        <td><span class="pill {{ $l->status }}">{{ __(\App\Models\Lead::STATUSES[$l->status]) }}</span></td>
        <td>{{ $l->source }}</td>
        <td class="r num">{{ $l->expected_value ? inr($l->expected_value) : '—' }}</td>
        <td class="nowrap">{{ $l->next_follow_up_on?->format('d M') ?? '—' }}</td>
        @if($me->canManage())<td>{{ $l->owner?->name ?? __('Unassigned') }}</td>@endif
        <td class="nowrap muted">{{ $l->updated_at->diffForHumans() }}</td>
      </tr>
    @empty
      <tr><td colspan="8" class="muted">{{ __('No leads found.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
  @include('partials.pager', ['p' => $leads])
</div>
@endsection
