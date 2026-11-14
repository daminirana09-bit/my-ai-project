@extends('layouts.app')
@section('title', __('Products'))
@section('content')
<div class="head">
  <div><h1>{{ __('Products') }}</h1><p>{{ __('Your product list with composition, packing, rate and MOQ, so every salesperson quotes the same.') }}</p></div>
  @if($canEdit)
  <div class="row">
    <a class="btn" href="{{ route('products.export') }}"><x-icon name="download"/>{{ __('Export Excel') }}</a>
    <a class="btn p" href="{{ route('products.create') }}"><x-icon name="plus"/>{{ __('Add product') }}</a>
  </div>
  @endif
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
  <h2>{{ __('Import products from Excel or CSV') }}</h2>
  <p class="small muted">{{ __('Columns: Name, Composition, Form, Packing, Category, Rate, Unit, MOQ, HSN, GST %, Active, Notes. A product with the same name and packing is updated, others are added.') }}</p>
  <div class="row"><div class="field"><label for="pfile">{{ __('File (.xlsx or .csv, up to 5 MB)') }}</label><input type="file" id="pfile" name="file" accept=".xlsx,.csv" required></div><button class="btn p"><x-icon name="upload"/>{{ __('Import') }}</button></div>
</form>
@endif
@endsection
