@extends('layouts.app')
@section('title', __('Import leads'))
@section('content')
@php $r = session('import'); @endphp
<div class="head"><div><h1>{{ __('Import leads') }}</h1><p>{{ __('Upload an Excel (.xlsx), CSV or ODS file. Column names are matched automatically, for example Firm name, Contact, Mobile, City, GST, Source, Status.') }}</p></div>
  <a class="btn" href="{{ route('leads.template') }}">{{ __('Download sample file') }}</a></div>

@if($r)
  <div class="card">
    <h2>{{ __('Import result') }}</h2>
    <div class="grid g4">
      <div class="kpi"><div class="l">{{ __('Added') }}</div><div class="v">{{ $r['created'] }}</div></div>
      <div class="kpi"><div class="l">{{ __('Already in your leads') }}</div><div class="v">{{ $r['already_yours'] }}</div></div>
      <div class="kpi {{ $r['clashes'] ? 'alert' : '' }}"><div class="l">{{ __('Clashes (sent to admin)') }}</div><div class="v">{{ $r['clashes'] }}</div></div>
      <div class="kpi"><div class="l">{{ __('Skipped') }}</div><div class="v">{{ $r['skipped'] }}</div><div class="s">{{ __('empty or repeated rows') }}</div></div>
    </div>
    @if($r['errors'])<div class="flash bad">@foreach($r['errors'] as $line => $e)<div>{{ __('Row :n', ['n' => $line]) }}: {{ $e }}</div>@endforeach</div>@endif
    @if($r['clashes'])<p class="small muted">{{ __('Some clients are already with another salesperson. They were not duplicated. Admin and managers will decide who keeps them.') }}</p>@endif
  </div>
@endif

<form method="post" action="{{ route('leads.import.run') }}" enctype="multipart/form-data" class="card form">
  @csrf
  <div class="field"><label for="file">{{ __('File (.xlsx, .csv or .ods, up to 10 MB)') }}</label><input type="file" id="file" name="file" accept=".xlsx,.csv,.ods,.tsv,.txt" required></div>
  @if($owners->isNotEmpty())
    <div class="field"><label for="owner_id">{{ __('Give these leads to') }}</label><select id="owner_id" name="owner_id"><option value="">{{ __('Me, or the "Salesperson" column in the file') }}</option>@foreach($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
  @else
    <p class="small muted">{{ __('Imported leads are added to your own leads.') }}</p>
  @endif
  <div><button class="btn p">{{ __('Import') }}</button></div>
</form>

<div class="card note">
  <h3>{{ __('From Google Sheets') }}</h3>
  <p>{{ __('In Google Sheets choose File → Download → Microsoft Excel (.xlsx) or CSV, then upload that file here. Your sheet does not have to be shared or made public.') }}</p>
</div>
@endsection
