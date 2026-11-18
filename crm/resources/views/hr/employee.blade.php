@extends('layouts.app')
@section('title', $u->name)
@section('content')
@php $v = fn ($g, $k) => old("$g.$k", $p->field($g, $k)); $saved = __('Saved. Leave empty to keep.'); $sal = fn ($k) => old("salary.$k", $p->pay($k)); @endphp
<div class="head"><div><h1>{{ $u->name }}</h1><p>{{ $u->email }} · {{ __(ucfirst($u->role)) }}</p></div><a class="btn" href="{{ route('hr.employees') }}">{{ __('All employees') }}</a></div>
@if($errors->any())<p class="flash bad">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('hr.employees.update', $u) }}" class="grid g2 stretch" autocomplete="off">
  @csrf @method('put')
  <section class="card form">
    <h2>{{ __('Job') }}</h2>
    <div class="f2">
      <div class="field"><label for="emp_code">{{ __('Employee code') }}</label><input id="emp_code" name="emp_code" maxlength="20" value="{{ old('emp_code', $p->emp_code) }}" placeholder="EMP-001"></div>
      <div class="field"><label for="designation">{{ __('Designation') }}</label><input id="designation" name="designation" maxlength="80" value="{{ old('designation', $p->designation) }}" placeholder="{{ __('e.g. Medical Representative') }}"></div>
      <div class="field"><label for="department">{{ __('Department') }}</label><input id="department" name="department" maxlength="80" value="{{ old('department', $p->department) }}"></div>
      <div class="field"><label for="headquarter">{{ __('Headquarter (HQ)') }}</label><input id="headquarter" name="headquarter" maxlength="80" value="{{ old('headquarter', $p->headquarter) }}"></div>
      <div class="field"><label for="employment_type">{{ __('Employment') }}</label><select id="employment_type" name="employment_type">@foreach(\App\Models\EmployeeProfile::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('employment_type', $p->employment_type ?? 'permanent') === $k)>{{ __($l) }}</option>@endforeach</select></div>
      <div class="field"><label for="manager_id">{{ __('Reports to') }}</label><select id="manager_id" name="manager_id"><option value="">—</option>@foreach($managers as $m)<option value="{{ $m->id }}" @selected((int) old('manager_id', $u->manager_id) === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
      <div class="field"><label for="date_of_joining">{{ __('Date of joining') }}</label><input type="date" id="date_of_joining" name="date_of_joining" value="{{ old('date_of_joining', $p->date_of_joining?->toDateString()) }}"></div>
      <div class="field"><label for="status">{{ __('Status') }}</label><select id="status" name="status">@foreach(\App\Models\EmployeeProfile::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $p->status ?? 'active') === $k)>{{ __($l) }}</option>@endforeach</select></div>
      <div class="field"><label for="exit_date">{{ __('Last working day (if leaving)') }}</label><input type="date" id="exit_date" name="exit_date" value="{{ old('exit_date', $p->exit_date?->toDateString()) }}"></div>
    </div>
    <h2>{{ __('Personal') }}</h2>
    <div class="f2">
      <div class="field"><label for="date_of_birth">{{ __('Date of birth') }}</label><input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $p->date_of_birth?->toDateString()) }}"></div>
      <div class="field"><label for="gender">{{ __('Gender') }}</label><select id="gender" name="gender"><option value="">—</option>@foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected(old('gender', $p->gender) === $k)>{{ __($l) }}</option>@endforeach</select></div>
      <div class="field"><label for="pphone">{{ __('Personal phone') }}</label><input id="pphone" name="personal[phone]" maxlength="20" value="{{ $v('personal', 'phone') }}"></div>
      <div class="field"><label for="pemail">{{ __('Personal email') }}</label><input type="email" id="pemail" name="personal[email]" maxlength="120" value="{{ $v('personal', 'email') }}"></div>
      <div class="field span2"><label for="paddress">{{ __('Address') }}</label><input id="paddress" name="personal[address]" maxlength="300" value="{{ $v('personal', 'address') }}"></div>
      <div class="field"><label for="ename">{{ __('Emergency contact') }}</label><input id="ename" name="personal[emergency_name]" maxlength="80" value="{{ $v('personal', 'emergency_name') }}"></div>
      <div class="field"><label for="ephone">{{ __('Emergency phone') }}</label><input id="ephone" name="personal[emergency_phone]" maxlength="20" value="{{ $v('personal', 'emergency_phone') }}"></div>
      <div class="field"><label for="blood">{{ __('Blood group') }}</label><input id="blood" name="personal[blood_group]" maxlength="5" value="{{ $v('personal', 'blood_group') }}"></div>
    </div>
  </section>
  <section class="card form">
    <h2>{{ __('Salary per month (₹)') }}</h2>
    <div class="f3">
      @foreach(\App\Models\EmployeeProfile::EARNINGS as $k => $l)
        <div class="field"><label for="s_{{ $k }}">{{ __($l) }}</label><input type="number" id="s_{{ $k }}" name="salary[{{ $k }}]" min="0" step="0.01" value="{{ $sal($k) }}"></div>
      @endforeach
      <div class="field"><label for="s_pt">{{ __('Professional tax') }}</label><input type="number" id="s_pt" name="salary[pt]" min="0" max="2500" step="1" value="{{ $sal('pt') }}" placeholder="{{ (int) \App\Models\CompanySetting::current()->hr('pt_default') }}"></div>
      <div class="field"><label for="s_tds">{{ __('TDS per month') }}</label><input type="number" id="s_tds" name="salary[tds]" min="0" step="1" value="{{ $sal('tds') }}"></div>
    </div>
    <label class="check"><input type="checkbox" name="salary[pf]" value="1" @checked(old('salary.pf', $p->pay('pf')))> {{ __('PF (12% of basic, up to ₹15,000)') }}</label>
    <label class="check"><input type="checkbox" name="salary[esi]" value="1" @checked(old('salary.esi', $p->pay('esi')))> {{ __('ESI (0.75% when gross is up to ₹21,000)') }}</label>
    <p class="small muted">{{ __('Monthly gross') }}: <b>₹{{ number_format($p->monthlyGross()) }}</b></p>
    <h2>{{ __('Bank') }}</h2>
    <div class="f2">
      <div class="field"><label for="bname">{{ __('Account holder name') }}</label><input id="bname" name="bank[account_name]" maxlength="100" value="{{ $v('bank', 'account_name') }}"></div>
      <div class="field"><label for="bacc">{{ __('Account number') }}</label><input id="bacc" name="bank[account_no]" inputmode="numeric" maxlength="20" placeholder="{{ $p->field('bank', 'account_no') ? $p->maskedAccount().' · '.$saved : '' }}"></div>
      <div class="field"><label for="bifsc">{{ __('IFSC') }}</label><input id="bifsc" name="bank[ifsc]" maxlength="11" value="{{ $v('bank', 'ifsc') }}" placeholder="SBIN0001234"></div>
      <div class="field"><label for="bbank">{{ __('Bank name') }}</label><input id="bbank" name="bank[bank_name]" maxlength="80" value="{{ $v('bank', 'bank_name') }}"></div>
    </div>
    <h2>{{ __('IDs') }}</h2>
    <div class="f2">
      @foreach(['pan' => ['PAN', 'ABCDE1234F'], 'uan' => ['UAN (PF)', '12 digits'], 'esic' => ['ESIC number', ''], 'aadhaar_last4' => ['Aadhaar: last 4 digits only', '1234']] as $k => [$l, $ph])
        <div class="field"><label for="id_{{ $k }}">{{ __($l) }}</label><input id="id_{{ $k }}" name="ids[{{ $k }}]" maxlength="17" placeholder="{{ $p->field('ids', $k) ? '••••'.substr($p->field('ids', $k), -2).' · '.$saved : $ph }}"></div>
      @endforeach
    </div>
    <div><button class="btn p">{{ __('Save employee details') }}</button></div>
  </section>
</form>
<section class="card form" id="documents">
  <h2>{{ __('Documents') }}</h2>
  <form method="post" action="{{ route('hr.documents.store', $u) }}" enctype="multipart/form-data" class="f3 bot-add">@csrf
    <div class="field"><label for="doc_title">{{ __('Title') }}</label><input id="doc_title" name="title" maxlength="120" required placeholder="{{ __('e.g. Offer letter') }}"></div>
    <div class="field"><label for="doc_file">{{ __('File (PDF, photo or Word, up to 10 MB)') }}</label><input type="file" id="doc_file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
    <div><button class="btn"><x-icon name="upload"/>{{ __('Upload') }}</button></div>
  </form>
  <div class="list">
    @forelse($docs as $d)
      <div class="item"><span class="bar"></span><div><b>{{ $d->title }}</b><small>{{ strtoupper($d->extension) }} · {{ $d->created_at->format('d M Y') }}</small></div>
        <div class="row"><a class="btn sm" href="{{ route('hr.documents.show', $d) }}"><x-icon name="download"/>{{ __('Open') }}</a>
          <form method="post" action="{{ route('hr.documents.destroy', $d) }}">@csrf @method('delete')<button class="btn sm bad" data-confirm="{{ __('Delete this document?') }}"><x-icon name="trash"/></button></form></div></div>
    @empty
      <p class="empty">{{ __('No documents yet.') }}</p>
    @endforelse
  </div>
</section>
@endsection
