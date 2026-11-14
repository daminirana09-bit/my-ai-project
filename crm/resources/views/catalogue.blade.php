@extends('layouts.app')
@section('title', __('Catalogue & files'))
@section('content')
<div class="head">
  <div><h1>{{ __('Catalogue & files') }}</h1><p>{{ __('Price lists, catalogues, visualisers and COAs for the whole team. Upload once: everyone sees the latest version at once.') }}</p></div>
  <div class="row">
    <a class="btn" href="{{ route('products.export', ['format' => 'pdf']) }}" target="_blank" rel="noopener"><x-icon name="download"/>{{ __('Price list PDF') }}</a>
    <a class="btn" href="{{ route('products.index') }}"><x-icon name="products"/>{{ __('Product list') }}</a>
  </div>
</div>
@error('file')<div class="flash bad">{{ $message }}</div>@enderror

@if($canEdit)
<form method="post" action="{{ route('catalogue.store') }}" enctype="multipart/form-data" class="card form">
  @csrf
  <h2>{{ __('Upload a file') }}</h2>
  <div class="f3">
    <div class="field"><label for="file">{{ __('File (PDF, Excel, CSV, ODS, Word, PowerPoint, photo; up to 25 MB)') }}</label><input type="file" id="file" name="file" required accept=".pdf,.xlsx,.xls,.csv,.ods,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.webp"></div>
    <div class="field"><label for="title">{{ __('Title (optional)') }}</label><input id="title" name="title" maxlength="150" placeholder="{{ __('e.g. Price list November 2026') }}"></div>
    <div class="field"><label for="category">{{ __('Group (optional)') }}</label><input id="category" name="category" maxlength="60" list="cats" placeholder="{{ __('e.g. Price lists, Catalogues, COA') }}"><datalist id="cats">@foreach($categories as $cat)<option value="{{ $cat }}">@endforeach</datalist></div>
  </div>
  <label class="check"><input type="checkbox" name="tell_team" value="1" checked> {{ __('Tell the whole team (alert)') }}</label>
  <div><button class="btn p"><x-icon name="upload"/>{{ __('Upload') }}</button></div>
</form>
@endif

<form method="get" class="card filters">
  <div class="field"><label for="q">{{ __('Search title or group') }}</label><input id="q" name="q" value="{{ request('q') }}"></div>
  <button class="btn"><x-icon name="search"/>{{ __('Filter') }}</button>
</form>

<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Group') }}</th><th>{{ __('Type') }}</th><th class="r">{{ __('Size') }}</th><th>{{ __('Updated') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($files as $f)
      <tr>
        <td><a href="{{ route('catalogue.download', [$f, 'open' => 1]) }}" target="_blank" rel="noopener"><b>{{ $f->title }}</b></a>
          <span class="sub">{{ $f->original_name }}@if($f->version > 1) · {{ __('version :v', ['v' => $f->version]) }}@endif · {{ __(':n downloads', ['n' => $f->downloads]) }}</span>
          @if(session('share_file') === $f->id && $f->shareActive())<p class="secret sm">{{ route('catalogue.shared', $f->share_token) }}</p>@endif
          @if($f->shareActive() && session('share_file') !== $f->id)<span class="sub">{{ __('Share link on until :d', ['d' => $f->share_expires_at->format('d M Y')]) }}</span>@endif
        </td>
        <td>{{ $f->category ?: '—' }}</td>
        <td><span class="pill">{{ __($f->kind()) }}</span></td>
        <td class="r num nowrap">{{ $f->sizeLabel() }}</td>
        <td class="nowrap muted">{{ $f->updated_at->diffForHumans() }}<span class="sub">{{ $f->uploader?->name }}</span></td>
        <td class="r">
          <div class="actions">
            <a class="btn sm" href="{{ route('catalogue.download', $f) }}"><x-icon name="download"/>{{ __('Download') }}</a>
            <form method="post" action="{{ route('catalogue.share', $f) }}" class="row inline">@csrf
              <label class="sr" for="days{{ $f->id }}">{{ __('Share link valid for') }}</label>
              <select id="days{{ $f->id }}" name="days" class="sm">@foreach([1 => __('1 day'), 7 => __('7 days'), 30 => __('30 days'), 90 => __('90 days')] as $d => $l)<option value="{{ $d }}" @selected($d === 7)>{{ $l }}</option>@endforeach</select>
              <button class="btn sm">{{ __('Share link') }}</button></form>
            @if($canEdit)
              @if(in_array($f->extension, \App\Models\ProductFile::SPREADSHEETS, true))
                <form method="post" action="{{ route('catalogue.import', $f) }}">@csrf<button class="btn sm" data-confirm="{{ __('Add and update products from this file?') }}">{{ __('Fill product list') }}</button></form>
              @endif
              <details class="more"><summary class="btn sm">{{ __('More') }}</summary>
                <div class="menu">
                  <form method="post" action="{{ route('catalogue.replace', $f) }}" enctype="multipart/form-data" class="form">@csrf
                    <label for="rep{{ $f->id }}" class="small">{{ __('Upload new version') }}</label><input type="file" id="rep{{ $f->id }}" name="file" required>
                    <button class="btn sm p">{{ __('Replace for everyone') }}</button></form>
                  @if($f->shareActive())<form method="post" action="{{ route('catalogue.unshare', $f) }}">@csrf<button class="btn sm">{{ __('Stop share link') }}</button></form>@endif
                  <form method="post" action="{{ route('catalogue.destroy', $f) }}">@csrf @method('delete')<button class="btn sm bad" data-confirm="{{ __('Delete this file for everyone?') }}"><x-icon name="trash"/>{{ __('Delete') }}</button></form>
                </div>
              </details>
            @endif
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="empty">{{ __('No files yet.') }} @if($canEdit){{ __('Upload your price list or catalogue above.') }}@endif</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<p class="small muted">{{ __('To send a file to a client: open the lead and use "Send on WhatsApp", or copy a share link into email or your own WhatsApp.') }}</p>
@endsection
