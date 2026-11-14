@extends('layouts.app')
@section('title', __('Targets & orders'))
@section('content')
@php $me = auth()->user(); $label = \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'); @endphp
<div class="head">
  <div><h1>{{ __('Targets & orders') }}</h1><p>{{ __('Target, sales done and pending for :month.', ['month' => $label]) }}</p></div>
  <form method="get" class="row"><label class="sr" for="month">{{ __('Month') }}</label><input type="month" id="month" name="month" value="{{ $month }}" data-autosubmit></form>
</div>

<form method="post" action="{{ route('targets.save', ['month' => $month]) }}" class="card">
  @csrf
  <div class="ch"><h2>{{ __('Salesperson-wise') }}</h2>@if($me->canManage())<button class="btn sm p">{{ __('Save targets') }}</button>@endif</div>
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Salesperson') }}</th><th class="r">{{ __('Target (₹)') }}</th><th class="r">{{ __('Sales done') }}</th><th class="r">{{ __('Still to achieve') }}</th><th class="r">{{ __('Pending orders') }}</th><th>{{ __('Progress') }}</th><th class="r">{{ __('Calls today') }}</th><th class="r">{{ __('Calls this week') }}</th><th class="r">{{ __('Leads won') }}</th></tr></thead>
    <tbody>
    @foreach($rows as $r)
      <tr>
        <td><b>{{ $r->user->name }}</b></td>
        <td class="r">@if($me->canManage())<label class="sr" for="t{{ $r->user->id }}">{{ __('Target') }}</label><input class="num r" type="number" min="0" step="1000" id="t{{ $r->user->id }}" name="targets[{{ $r->user->id }}]" value="{{ $r->target ?: '' }}">@else{{ inr($r->target) }}@endif</td>
        <td class="r num">{{ inr($r->achieved) }}</td>
        <td class="r num">{{ inr($r->remaining) }}</td>
        <td class="r num">{{ inr($r->pending_orders) }}</td>
        <td><meter min="0" max="{{ max($r->target, 1) }}" value="{{ $r->achieved }}" low="{{ $r->target * .5 }}" high="{{ $r->target * .8 }}" optimum="{{ $r->target }}"></meter><span class="sub">{{ $r->percent !== null ? $r->percent.'%' : __('No target') }}</span></td>
        <td class="r num">{{ $r->calls_today }}</td>
        <td class="r num">{{ $r->calls_week }}</td>
        <td class="r num">{{ $r->leads_won }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</form>

<div class="grid g21">
  <div class="card">
    <h2>{{ __('Sales in :month', ['month' => $label]) }}</h2>
    <div class="tbl"><table>
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Salesperson') }}</th><th>{{ __('Client') }}</th><th>{{ __('Invoice') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
      @forelse($sales as $s)
        <tr>
          <td class="nowrap">{{ $s->sale_date->format('d M') }}</td><td>{{ $s->user?->name }}</td><td>{{ $s->lead?->firm_name ?? '—' }}</td><td class="mono">{{ $s->invoice_no ?: '—' }}</td>
          <td class="r num">{{ inr($s->amount, false) }}</td>
          <td>
            @if($me->canManage())
              <form method="post" action="{{ route('sales.update', $s) }}">@csrf @method('patch')
                <label class="sr" for="ss{{ $s->id }}">{{ __('Status') }}</label>
                <select id="ss{{ $s->id }}" name="status" data-autosubmit>@foreach(\App\Models\Sale::STATUSES as $k => $l)<option value="{{ $k }}" @selected($s->status === $k)>{{ __($l) }}</option>@endforeach</select>
              </form>
            @else
              <span class="pill {{ $s->status === 'confirmed' ? 'good' : 'warn' }}">{{ __(\App\Models\Sale::STATUSES[$s->status]) }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="muted">{{ __('No sales recorded this month.') }}</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </div>
  <form method="post" action="{{ route('sales.store') }}" class="card form">
    @csrf
    <h2>{{ __('Record a sale') }}</h2>
    @if($me->canManage())
      <div class="field"><label for="user_id">{{ __('Salesperson') }}</label><select id="user_id" name="user_id">@foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label for="lead_id">{{ __('Client') }}</label><select id="lead_id" name="lead_id"><option value="">—</option>@foreach($myLeads as $l)<option value="{{ $l->id }}">{{ $l->firm_name }}</option>@endforeach</select></div>
    <div class="f2">
      <div class="field"><label for="amount">{{ __('Amount (₹)') }}</label><input type="number" min="1" step="1" id="amount" name="amount" required></div>
      <div class="field"><label for="sale_date">{{ __('Date') }}</label><input type="date" id="sale_date" name="sale_date" value="{{ now()->format('Y-m-d') }}" required></div>
      <div class="field"><label for="invoice_no">{{ __('Invoice no.') }}</label><input id="invoice_no" name="invoice_no"></div>
      <div class="field"><label for="s_status">{{ __('Status') }}</label><select id="s_status" name="status" @disabled($me->isRep())>@foreach(\App\Models\Sale::STATUSES as $k => $l)<option value="{{ $k }}" @selected($me->isRep() ? $k === 'pending' : $k === 'confirmed')>{{ __($l) }}</option>@endforeach</select></div>
    </div>
    @if($me->isRep())<input type="hidden" name="status" value="pending"><p class="small muted">{{ __('Your manager confirms the sale before it counts towards your target.') }}</p>@endif
    <div><button class="btn p">{{ __('Save sale') }}</button></div>
  </form>
</div>
@endsection
