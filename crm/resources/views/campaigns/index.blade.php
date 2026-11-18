@extends('layouts.app')
@section('title', __('Marketing'))
@section('content')
<div class="head"><div><h1>{{ __('Marketing') }}</h1><p>{{ __('Broadcast an approved WhatsApp template to thousands of clients: by status, tag, city, language or salesperson, now or at a set time, with a photo or PDF. Replies land in the WhatsApp inbox; clients who reply STOP never get a campaign again.') }}</p></div>
  <a class="btn p" href="{{ route('campaigns.create') }}"><x-icon name="plus"/>{{ __('New campaign') }}</a></div>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Template') }}</th><th class="r">{{ __('Clients') }}</th><th class="r">{{ __('Sent') }}</th><th class="r">{{ __('Read') }}</th><th class="r">{{ __('Failed') }}</th><th class="r">{{ __('Replied') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse($campaigns as $c)
      <tr><td><a href="{{ route('campaigns.show', $c) }}"><b>{{ $c->name }}</b></a><span class="sub">{{ $c->number?->label }} · {{ $c->creator?->name }} · {{ $c->created_at->format('d M Y') }}</span></td>
        <td class="mono">{{ $c->template_name }} <span class="muted">{{ $c->template_language }}</span></td>
        <td class="r num">{{ $c->total }}</td><td class="r num">{{ $c->sent }}</td><td class="r num">{{ $c->read }}</td><td class="r num">{{ $c->failed }}</td><td class="r num">{{ $c->replied }}</td>
        <td><span class="pill {{ $c->status === 'done' ? 'good' : 'warn' }}">{{ __(\App\Models\Campaign::STATUSES[$c->status] ?? $c->status) }}</span>@if($c->status === 'scheduled')<span class="sub">{{ $c->scheduled_at?->format('d M, g:i A') }}</span>@endif</td></tr>
    @empty
      <tr><td colspan="8" class="empty">{{ __('No campaigns yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@include('partials.pager', ['p' => $campaigns])
@endsection
