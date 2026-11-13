@extends('layouts.app')
@section('title', __('Activity log'))
@section('content')
<div class="head"><div><h1>{{ __('Activity log') }}</h1><p>{{ __('Logins, exports, imports, assignments and settings changes. Nobody can edit or delete this list from the app.') }}</p></div></div>
<div class="card"><div class="tbl"><table>
  <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('What') }}</th><th>{{ __('Details') }}</th><th>IP</th></tr></thead>
  <tbody>
  @foreach($logs as $l)
    <tr><td class="nowrap">{{ $l->created_at?->format('d M Y, h:i A') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td class="mono">{{ $l->action }}</td><td class="small">{{ $l->subject }} @if($l->meta)<span class="sub mono">{{ json_encode($l->meta, JSON_UNESCAPED_UNICODE) }}</span>@endif</td><td class="mono small">{{ $l->ip }}</td></tr>
  @endforeach
  </tbody>
</table></div>@include('partials.pager', ['p' => $logs])</div>
@endsection
