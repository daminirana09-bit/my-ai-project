@extends('layouts.app')
@section('title', $lead->exists ? __('Edit lead') : __('New lead'))
@section('content')
<div class="head"><h1>{{ $lead->exists ? __('Edit lead') : __('New lead') }}</h1></div>
<form method="post" action="{{ $lead->exists ? route('leads.update', $lead) : route('leads.store') }}" class="card form">
  @csrf @if($lead->exists) @method('put') @endif
  <div class="f3">
    <div class="field"><label for="firm_name">{{ __('Firm name') }} *</label><input id="firm_name" name="firm_name" value="{{ old('firm_name', $lead->firm_name) }}" required></div>
    <div class="field"><label for="contact_name">{{ __('Contact person') }}</label><input id="contact_name" name="contact_name" value="{{ old('contact_name', $lead->contact_name) }}"></div>
    <div class="field"><label for="phone">{{ __('Phone / WhatsApp') }}</label><input id="phone" name="phone" value="{{ old('phone', $lead->phone) }}" inputmode="tel"></div>
    <div class="field"><label for="email">{{ __('Email') }}</label><input type="email" id="email" name="email" value="{{ old('email', $lead->email) }}"></div>
    <div class="field"><label for="city">{{ __('City') }}</label><input id="city" name="city" value="{{ old('city', $lead->city) }}"></div>
    <div class="field"><label for="state">{{ __('State') }}</label><input id="state" name="state" value="{{ old('state', $lead->state) }}"></div>
    <div class="field"><label for="gstin">GSTIN</label><input id="gstin" name="gstin" value="{{ old('gstin', $lead->gstin) }}"></div>
    <div class="field"><label for="source">{{ __('Source') }}</label><select id="source" name="source"><option value="">—</option>@foreach(collect(\App\Models\MasterOption::values('source'))->push($lead->source)->filter()->unique() as $s)<option @selected(old('source', $lead->source) === $s)>{{ $s }}</option>@endforeach</select></div>
    <div class="field"><label for="client_type">{{ __('Client type') }}</label><select id="client_type" name="client_type"><option value="">—</option>@foreach(collect(\App\Models\MasterOption::values('client_type'))->push($lead->client_type)->filter()->unique() as $s)<option @selected(old('client_type', $lead->client_type) === $s)>{{ $s }}</option>@endforeach</select></div>
    <div class="field"><label for="status">{{ __('Status') }}</label><select id="status" name="status">@foreach(\App\Models\Lead::STATUSES as $k => $label)<option value="{{ $k }}" @selected(old('status', $lead->status) === $k)>{{ __($label) }}</option>@endforeach</select></div>
    <div class="field"><label for="temperature">{{ __('Hot / warm / cold') }}</label><select id="temperature" name="temperature"><option value="">—</option>@foreach(\App\Models\Lead::TEMPERATURES as $k => $label)<option value="{{ $k }}" @selected(old('temperature', $lead->temperature) === $k)>{{ __($label) }}</option>@endforeach</select></div>
    <div class="field"><label for="expected_value">{{ __('Expected value (₹)') }}</label><input type="number" min="0" step="1" id="expected_value" name="expected_value" value="{{ old('expected_value', $lead->expected_value) }}"></div>
    <div class="field"><label for="next_follow_up_on">{{ __('Next follow-up') }}</label><input type="date" id="next_follow_up_on" name="next_follow_up_on" value="{{ old('next_follow_up_on', $lead->next_follow_up_on?->format('Y-m-d')) }}"></div>
  </div>
  <div class="f2">
    <div class="field"><label for="tags">{{ __('Tags (comma separated)') }}</label><input id="tags" name="tags" value="{{ old('tags', implode(', ', $lead->tags ?? [])) }}" placeholder="{{ __('e.g. PCD, Diwali offer, North zone') }}"></div>
    <div class="field"><label for="referred_by">{{ __('Referred by (if it came by reference)') }}</label><input id="referred_by" name="referred_by" value="{{ old('referred_by', $lead->referred_by) }}" maxlength="150"></div>
  </div>
  <div class="field"><label for="requirement">{{ __('Requirement') }}</label><input id="requirement" name="requirement" value="{{ old('requirement', $lead->requirement) }}" placeholder="{{ __('e.g. PCD for 40 products, or Pan-D 50,000 strips') }}"></div>
  @if(! $lead->exists && $owners->isNotEmpty())
    <div class="field"><label for="owner_id">{{ __('Assign to') }}</label><select id="owner_id" name="owner_id">@foreach($owners as $o)<option value="{{ $o->id }}" @selected((int) old('owner_id', auth()->id()) === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
  @endif
  <div class="field"><label for="lost_reason">{{ __('Reason if lost') }}</label><input id="lost_reason" name="lost_reason" list="lost_reasons" value="{{ old('lost_reason', $lead->lost_reason) }}"><datalist id="lost_reasons">@foreach(\App\Models\MasterOption::values('lost_reason') as $r)<option value="{{ $r }}">@endforeach</datalist></div>
  <div class="field"><label for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes">{{ old('notes', $lead->notes) }}</textarea></div>
  <p class="small muted">{{ __('If this client is already with another salesperson, the lead is not duplicated. Admin and managers get a clash alert.') }}</p>
  <div class="row"><button class="btn p">{{ __('Save') }}</button><a class="btn" href="{{ $lead->exists ? route('leads.show', $lead) : route('leads.index') }}">{{ __('Cancel') }}</a></div>
</form>
@endsection
