@extends('layouts.app')
@section('title', __('Employees'))
@section('content')
<div class="head"><div><h1>{{ __('Employees') }}</h1><p>{{ __('Job details, bank, IDs, salary and documents of everyone with a login. Bank and ID numbers are stored encrypted and never shown in full.') }}</p></div>
  <a class="btn" href="{{ route('users.create') }}"><x-icon name="plus"/>{{ __('Add a person') }}</a></div>
<section class="card flush">
  <div class="tbl"><table>
    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Designation') }}</th><th>{{ __('HQ') }}</th><th>{{ __('Joined') }}</th><th class="r">{{ __('Monthly gross') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @foreach($people as $u)
      @php $p = $u->profile; @endphp
      <tr><td><a href="{{ route('hr.employees.edit', $u) }}"><b>{{ $u->name }}</b></a><span class="sub">{{ __(ucfirst($u->role)) }}@unless($u->active) · {{ __('Login off') }}@endunless</span></td>
        <td class="mono">{{ $p?->emp_code ?? '—' }}</td><td>{{ $p?->designation ?? '—' }}</td><td>{{ $p?->headquarter ?? '—' }}</td>
        <td>{{ $p?->date_of_joining?->format('d M Y') ?? '—' }}</td><td class="r num">{{ $p && $p->monthlyGross() > 0 ? '₹'.number_format($p->monthlyGross()) : '—' }}</td>
        <td>@if($p)<span class="pill {{ $p->status === 'active' ? 'good' : ($p->status === 'notice' ? 'warn' : 'bad') }}">{{ __(\App\Models\EmployeeProfile::STATUSES[$p->status]) }}</span>@else<span class="pill warn">{{ __('Details missing') }}</span>@endif</td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endsection
