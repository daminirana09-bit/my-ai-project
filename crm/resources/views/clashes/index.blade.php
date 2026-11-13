@extends('layouts.app')
@section('title', __('Lead clashes'))
@section('content')
<div class="head"><div><h1>{{ __('Lead clashes') }}</h1><p>{{ __('The same client (same phone or GSTIN) was added, imported or contacted by two salespeople. Choose who keeps it.') }}</p></div></div>
<div class="card">
  <h2>{{ __('Open') }} ({{ $open->count() }})</h2>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Has it now') }}</th><th>{{ __('Also tried') }}</th><th>{{ __('What happened') }}</th><th>{{ __('When') }}</th><th>{{ __('Give lead to') }}</th></tr></thead>
    <tbody>
    @forelse($open as $c)
      <tr>
        <td><a href="{{ route('leads.show', $c->lead_id) }}"><b>{{ $c->lead?->firm_name }}</b></a><span class="sub">{{ $c->lead?->city }}</span></td>
        <td>{{ $c->existingOwner?->name ?? '—' }}</td>
        <td>{{ $c->otherUser?->name ?? '—' }}</td>
        <td>{{ __(\App\Models\LeadClash::REASONS[$c->reason] ?? $c->reason) }}<span class="sub">{{ $c->detail }}</span></td>
        <td class="nowrap">{{ $c->created_at->diffForHumans() }}</td>
        <td>
          <form method="post" action="{{ route('clashes.resolve', $c) }}" class="row">
            @csrf
            <label class="sr" for="to{{ $c->id }}">{{ __('Give lead to') }}</label>
            <select id="to{{ $c->id }}" name="assigned_to">
              @foreach($owners as $o)<option value="{{ $o->id }}" @selected($o->id === $c->existing_owner_id)>{{ $o->name }}</option>@endforeach
            </select>
            <button class="btn sm p">{{ __('Decide') }}</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">{{ __('No open clashes.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2>{{ __('Recently decided') }}</h2>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Given to') }}</th><th>{{ __('Decided by') }}</th><th>{{ __('When') }}</th></tr></thead>
    <tbody>
    @forelse($resolved as $c)
      <tr><td>{{ $c->lead?->firm_name }}</td><td>{{ $c->assignedTo?->name }}</td><td>{{ $c->resolver?->name }}</td><td>{{ $c->resolved_at?->diffForHumans() }}</td></tr>
    @empty
      <tr><td colspan="4" class="muted">{{ __('Nothing yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</div>
@endsection
