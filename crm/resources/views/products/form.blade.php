@extends('layouts.app')
@section('title', $product->exists ? __('Edit product') : __('Add product'))
@section('content')
<div class="head"><h1>{{ $product->exists ? $product->name : __('Add product') }}</h1></div>
<form method="post" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="card form">
  @csrf @if($product->exists) @method('put') @endif
  <div class="f2">
    <div class="field"><label for="name">{{ __('Product name') }} *</label><input id="name" name="name" value="{{ old('name', $product->name) }}" required></div>
    <div class="field"><label for="composition">{{ __('Composition') }}</label><input id="composition" name="composition" value="{{ old('composition', $product->composition) }}" placeholder="{{ __('e.g. Pantoprazole 40 mg + Domperidone 30 mg SR') }}"></div>
  </div>
  <div class="f4">
    <div class="field"><label for="form">{{ __('Form') }}</label><select id="form" name="form"><option value="">—</option>@foreach(\App\Models\Product::FORMS as $f)<option @selected(old('form', $product->form) === $f)>{{ $f }}</option>@endforeach</select></div>
    <div class="field"><label for="packing">{{ __('Packing') }}</label><input id="packing" name="packing" value="{{ old('packing', $product->packing) }}" placeholder="10×10 Alu-Alu"></div>
    <div class="field"><label for="category">{{ __('Category') }}</label><select id="category" name="category"><option value="">—</option>@foreach(\App\Models\MasterOption::values('product_category') as $c)<option @selected(old('category', $product->category) === $c)>{{ $c }}</option>@endforeach</select></div>
    <div class="field"><label for="unit">{{ __('Unit') }}</label><select id="unit" name="unit">@foreach(['strip', 'bottle', 'box', 'piece', 'kg', 'litre'] as $u)<option value="{{ $u }}" @selected(old('unit', $product->unit) === $u)>{{ __($u) }}</option>@endforeach</select></div>
    <div class="field"><label for="rate">{{ __('Rate (₹ per unit)') }}</label><input type="number" min="0" step="0.01" id="rate" name="rate" value="{{ old('rate', $product->rate) }}"></div>
    <div class="field"><label for="moq">MOQ</label><input type="number" min="0" step="1" id="moq" name="moq" value="{{ old('moq', $product->moq) }}"></div>
    <div class="field"><label for="hsn">HSN</label><input id="hsn" name="hsn" value="{{ old('hsn', $product->hsn ?? '3004') }}"></div>
    <div class="field"><label for="gst_percent">GST %</label><input type="number" min="0" max="28" step="0.01" id="gst_percent" name="gst_percent" value="{{ old('gst_percent', $product->gst_percent ?? 5) }}" required></div>
  </div>
  <div class="field"><label for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes">{{ old('notes', $product->notes) }}</textarea></div>
  <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $product->active ?? true))> {{ __('Active (shown to the sales team)') }}</label>
  <div class="row"><button class="btn p">{{ __('Save') }}</button><a class="btn" href="{{ route('products.index') }}">{{ __('Cancel') }}</a></div>
</form>
@endsection
