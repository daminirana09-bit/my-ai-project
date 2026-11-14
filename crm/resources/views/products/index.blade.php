@extends('layouts.app')
@section('title', __('Products'))
@section('content')
<div class="head">
  <div><h1>{{ __('Products') }}</h1><p>{{ __('Your product list with composition, packing, rate and MOQ, so every salesperson quotes the same.') }}</p></div>
  <div class="row">
    <a class="btn" href="{{ route('products.export', ['format' => 'pdf']) }}" target="_blank" rel="noopener"><x-icon name="download"/>{{ __('Price list PDF') }}</a>
    <a class="btn" href="{{ route('catalogue.index') }}"><x-icon name="folder"/>{{ __('Catalogue & files') }}</a>
    @if($canEdit)
      <details class="more"><summary class="btn"><x-icon name="download"/>{{ __('Export') }}</summary><div class="menu">
        @foreach(\App\Services\Sheets::EXPORT as $fmt => $label)<a class="btn sm" href="{{ route('products.export', ['format' => $fmt]) }}">{{ $label }} (.{{ $fmt }})</a>@endforeach
      </div></details>
      <a class="btn p" href="{{ route('products.create') }}"><x-icon name="plus"/>{{ __('Add product') }}</a>
    @endif
  </div>
</div>
<form method="get" class="card filters">
  <div class="field"><label for="q">{{ __('Search name, composition or packing') }}</label><input id="q" name="q" value="{{ request('q') }}"></div>
  <div class="field"><label for="category">{{ __('Category') }}</label><select id="category" name="category"><option value="">{{ __('All') }}</option>@foreach($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
  @if($canEdit)<label class="check"><input type="checkbox" name="all" value="1" @checked(request('all'))> {{ __('Show inactive too') }}</label>@endif
  <button class="btn"><x-icon name="search"/>{{ __('Filter') }}</button>
</form>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Form') }}</th><th>{{ __('Packing') }}</th><th>{{ __('Category') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">MOQ</th><th class="r">GST</th>@if($canEdit)<th></th>@endif</tr></thead>
    <tbody>
    @forelse($products as $p)
      <tr>
        <td><b>{{ $p->name }}</b>@unless($p->active) <span class="pill bad">{{ __('Inactive') }}</span>@endunless<span class="sub">{{ $p->composition }}</span></td>
        <td>{{ $p->form ?: '—' }}</td><td>{{ $p->packing ?: '—' }}</td><td>{{ $p->category ?: '—' }}</td>
        <td class="r num nowrap">{{ $p->rate !== null ? '₹'.number_format((float) $p->rate, 2).' / '.__($p->unit) : '—' }}</td>
        <td class="r num">{{ $p->moq ? number_format($p->moq) : '—' }}</td>
        <td class="r num">{{ rtrim(rtrim(number_format((float) $p->gst_percent, 2), '0'), '.') }}%</td>
        @if($canEdit)<td class="r"><a class="btn sm" href="{{ route('products.edit', $p) }}"><x-icon name="edit"/>{{ __('Edit') }}</a></td>@endif
      </tr>
    @empty
      <tr><td colspan="8" class="empty">{{ __('No products yet.') }}</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@include('partials.pager', ['p' => $products])
@if($canEdit)
<form method="post" action="{{ route('products.import') }}" enctype="multipart/form-data" class="card form">
  @csrf
  <h2>{{ __('Import products from Excel, CSV or ODS') }}</h2>
  <p class="small muted">{{ __('Columns: Name, Composition, Form, Packing, Category, Rate, Unit, MOQ, HSN, GST %, Active, Notes. A product with the same name and packing is updated, others are added.') }}</p>
  <p class="small muted">{{ __('From Google Sheets: File → Download → .xlsx or .ods. A PDF price list cannot be read into rows; keep it under Catalogue & files to share it.') }}</p>
  <div class="row"><div class="field"><label for="pfile">{{ __('File (.xlsx, .csv or .ods, up to 10 MB)') }}</label><input type="file" id="pfile" name="file" accept=".xlsx,.csv,.ods,.tsv,.txt" required></div><button class="btn p"><x-icon name="upload"/>{{ __('Import') }}</button></div>
</form>
@endif
@endsection
