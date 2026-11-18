@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
<div class="head"><div><h1>{{ $campaign->name }} <span class="pill {{ $campaign->status === 'done' ? 'good' : (in_array($campaign->status, ['paused', 'cancelled', 'limit'], true) ? 'warn' : 'accent') }}">{{ __(\App\Models\Campaign::STATUSES[$campaign->status] ?? $campaign->status) }}</span></h1>
  <p><span class="mono">{{ $campaign->template_name }}</span> · {{ $campaign->number?->label }} · {{ $campaign->scheduled_at?->isFuture() ? ($campaign->status === 'cancelled' ? __('Was to start') : __('Starts')).' '.$campaign->scheduled_at->format('d M Y, g:i A') : __('Started').' '.$campaign->started_at?->format('d M Y, g:i A') }}@if($campaign->finished_at) · {{ __('Finished') }} {{ $campaign->finished_at->format('d M, g:i A') }}@endif @if($campaign->header_type) · {{ $campaign->header_type === 'image' ? __('With photo') : __('With PDF') }}@endif</p></div>
  <div class="row">
    @if($campaign->isRunning())<form method="post" action="{{ route('campaigns.control', [$campaign, 'pause']) }}">@csrf<button class="btn"><x-icon name="pause"/>{{ __('Pause') }}</button></form>@endif
    @if($campaign->status === 'paused')<form method="post" action="{{ route('campaigns.control', [$campaign, 'resume']) }}">@csrf<button class="btn p">{{ __('Resume') }}</button></form>@endif
    @if((int) ($counts['failed'] ?? 0) > 0 && $campaign->status !== 'cancelled')<form method="post" action="{{ route('campaigns.control', [$campaign, 'retry']) }}">@csrf<button class="btn">{{ __('Send again to failed') }}</button></form>@endif
    @if(in_array($campaign->status, ['scheduled', 'sending', 'limit', 'paused'], true))<form method="post" action="{{ route('campaigns.control', [$campaign, 'cancel']) }}">@csrf<button class="btn bad" data-confirm="{{ __('Cancel this campaign? Clients not reached yet will not get it.') }}">{{ __('Cancel') }}</button></form>@endif
    <a class="btn" href="{{ route('campaigns.show', $campaign) }}">{{ __('Refresh') }}</a>
  </div></div>
@error('campaign')<p class="flash bad">{{ $message }}</p>@enderror
@if($campaign->status === 'limit')<p class="flash warn">{{ __('WhatsApp\'s 24-hour limit is reached. Sending continues by itself as the limit frees up.') }}</p>@endif
@if($campaign->status === 'scheduled')<p class="flash ok">{{ __('Will start by itself on :d.', ['d' => $campaign->scheduled_at?->format('d M Y, g:i A')]) }}</p>@endif
<section class="kpis">
  @foreach(['pending' => 'Waiting', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'skipped' => 'Skipped'] as $k => $l)
    <div class="card kpi"><span class="l">{{ __($l) }}</span><span class="v">{{ (int) ($counts[$k] ?? 0) }}</span></div>
  @endforeach
</section>
@php $reached = (int) (($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0)); @endphp
<section class="kpis">
  <div class="card kpi"><span class="l">{{ __('Replied') }}</span><span class="v">{{ $replied }}</span><span class="small muted">{{ $reached ? round($replied * 100 / $reached).'%' : '—' }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Ordered since') }}</span><span class="v">{{ $converted }}</span><span class="small muted">{{ $reached ? round($converted * 100 / $reached, 1).'%' : '—' }}</span></div>
  <div class="card kpi"><span class="l">{{ __('Order value since') }}</span><span class="v">₹{{ number_format($revenue) }}</span></div>
</section>
<div class="chips">@foreach([null => 'Latest', 'replied' => 'Replied', 'failed' => 'Failed / skipped'] as $k => $l)<a class="chip {{ request('show') === $k ? 'on' : '' }}" href="{{ route('campaigns.show', array_filter(['campaign' => $campaign->id, 'show' => $k])) }}">{{ __($l) }}</a>@endforeach</div>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Status') }}</th><th>{{ __('Replied') }}</th><th>{{ __('Details') }}</th></tr></thead>
    <tbody>@foreach($rows as $r)<tr><td>{{ $r->lead?->firm_name }}<span class="sub">{{ $r->lead?->city }}</span></td><td><span class="pill {{ in_array($r->status, ['failed', 'skipped']) ? 'bad' : ($r->status === 'read' ? 'good' : 'accent') }}">{{ __(ucfirst($r->status)) }}</span></td><td class="small">{{ $r->replied_at?->format('d M, g:i A') ?? '—' }}</td><td class="small muted">{{ $r->error }}</td></tr>@endforeach</tbody>
  </table></div>
</section>
@endsection
