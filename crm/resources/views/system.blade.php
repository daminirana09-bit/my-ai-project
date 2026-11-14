@extends('layouts.app')
@section('title', __('System check'))
@section('content')
@php $bad = collect($checks)->where(0, 'bad')->count(); $warn = collect($checks)->where(0, 'warn')->count(); @endphp
<div class="head"><div><h1>{{ __('System check') }}</h1><p>{{ __('Run this after installing on hosting. Red must be fixed; amber is recommended.') }}</p></div>
  <span class="pill {{ $bad ? 'bad' : ($warn ? 'warn' : 'good') }}">{{ $bad ? __(':n to fix', ['n' => $bad]) : ($warn ? __(':n suggestions', ['n' => $warn]) : __('All good')) }}</span></div>
<section class="card flush"><div class="tbl"><table>
  <thead><tr><th>{{ __('Status') }}</th><th>{{ __('Check') }}</th><th>{{ __('Details') }}</th></tr></thead>
  <tbody>@foreach($checks as [$s, $name, $detail])<tr><td><span class="pill {{ $s === 'ok' ? 'good' : ($s === 'warn' ? 'warn' : 'bad') }}">{{ $s === 'ok' ? 'OK' : ($s === 'warn' ? __('Check') : __('Fix')) }}</span></td><td>{{ $name }}</td><td class="small muted">{{ $detail }}</td></tr>@endforeach</tbody>
</table></div></section>
@endsection
