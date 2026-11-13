@extends('layouts.app')
@section('title', __('Monthly register'))
@section('content')
@php $code = ['full_day' => 'F', 'half_day' => 'H', 'absent' => 'A', 'single_swipe' => 'S', 'weekly_off' => 'W', 'leave' => 'L', 'present' => 'P']; @endphp
<div class="head"><div><h1>{{ __('Monthly register') }}</h1><p>{{ __('F full day · H half day · A absent · S single swipe · W weekly off · L leave') }}</p></div>
  <form method="get" class="row"><label class="sr" for="month">{{ __('Month') }}</label><input type="month" id="month" name="month" value="{{ $start->format('Y-m') }}" data-autosubmit></form></div>
<div class="card"><div class="tbl"><table class="reg">
  <thead><tr><th>{{ __('Name') }}</th>@for($d = $start; $d->lte($end); $d = $d->addDay())<th>{{ $d->day }}</th>@endfor<th>F</th><th>H</th><th>A</th><th>S</th></tr></thead>
  <tbody>
  @foreach($people as $p)
    @php $row = $days->get($p->id, collect()); $count = $row->countBy('status'); @endphp
    <tr>
      <td><a href="{{ route('attendance.person', [$p, 'month' => $start->format('Y-m')]) }}">{{ $p->name }}</a></td>
      @for($d = $start; $d->lte($end); $d = $d->addDay())
        @php $x = $row->get($d->toDateString()); $c = $x ? ($code[$x->status] ?? '?') : ''; @endphp
        <td class="{{ $c }}">{{ $c }}</td>
      @endfor
      <td class="F">{{ $count['full_day'] ?? 0 }}</td><td class="H">{{ $count['half_day'] ?? 0 }}</td><td class="A">{{ $count['absent'] ?? 0 }}</td><td class="S">{{ $count['single_swipe'] ?? 0 }}</td>
    </tr>
  @endforeach
  </tbody>
</table></div></div>
@endsection
