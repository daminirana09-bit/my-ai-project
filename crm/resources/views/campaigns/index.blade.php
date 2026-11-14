@extends('layouts.app')
@section('title', __('Marketing'))
@section('content')
<div class="head"><div><h1>{{ __('Marketing') }}</h1><p>{{ __('Send an approved WhatsApp template to a chosen group of clients. Clients who reply STOP never get a campaign again.') }}</p></div>
  <a class="btn p" href="{{ route('campaigns.create') }}"><x-icon name="plus"/>{{ __('New campaign') }}</a></div>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Template') }}</th><th class="r">{{ __('Clients') }}</th><th class="r">{{ __('Sent') }}</th><th class="r">{{ __('Read') }}</th><th class="r">{{ __('Failed') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse($campaigns as $c)
      <tr><td><a href="{{ route('campaigns.show', $c) }}"><b>{{ $c->name }}</b></a><span class="sub">{{ $c->number?->label }} · {{ $c->creator?->name }} · {{ $c->created_at->format('d M Y') }}</span></td>
        <td class="mono">{{ $c->template_name }} <span class="muted">{{ $c->template_language }}</span></td>
        <td class="r num">{{ $c->total }}</td><td class="r num">{{ $c->sent }}</td><td class="r num">{{ $c->read }}</td><td class="r num">{{ $c->failed }}</td>
        <td><span class="pill {{ $c->status === 'done' ? 'good' : 'warn' }}">{{ $c->status === 'done' ? __('Finished') : __('Sending') }}</span></td></tr>
    @empty
      <tr><td colspan="7" class="empty">{{ __('No campaigns yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@include('partials.pager', ['p' => $campaigns])
@endsection
