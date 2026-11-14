@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
<div class="head"><div><h1>{{ $campaign->name }}</h1><p><span class="mono">{{ $campaign->template_name }}</span> · {{ $campaign->number?->label }} · {{ $campaign->started_at?->format('d M Y, g:i A') }}</p></div>
  <a class="btn" href="{{ route('campaigns.show', $campaign) }}">{{ __('Refresh') }}</a></div>
<section class="kpis">
  @foreach(['pending' => 'Waiting', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'skipped' => 'Skipped'] as $k => $l)
    <div class="card kpi"><span class="l">{{ __($l) }}</span><span class="v">{{ (int) ($counts[$k] ?? 0) }}</span></div>
  @endforeach
</section>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Status') }}</th><th>{{ __('Details') }}</th></tr></thead>
    <tbody>@foreach($rows as $r)<tr><td>{{ $r->lead?->firm_name }}<span class="sub">{{ $r->lead?->city }}</span></td><td><span class="pill {{ in_array($r->status, ['failed', 'skipped']) ? 'bad' : ($r->status === 'read' ? 'good' : 'accent') }}">{{ __(ucfirst($r->status)) }}</span></td><td class="small muted">{{ $r->error }}</td></tr>@endforeach</tbody>
  </table></div>
</section>
@endsection
