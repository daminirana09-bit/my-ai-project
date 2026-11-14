@php $u = $u ?? auth()->user(); $others = \App\Models\User::active()->where('id', '!=', $u->id)->orderBy('name')->get(['id', 'name']); @endphp
<section class="card form">
  <div class="ch"><h2>{{ __('My availability') }}</h2>
    @if($u->isAway())<span class="pill warn">{{ __(\App\Models\User::AWAY_TYPES[$u->away_type]) }} · {{ __('until :t', ['t' => $u->away_until->format('d M, g:i A')]) }}</span>@else<span class="pill good">{{ __('Available') }}</span>@endif
  </div>
  @if($u->isAway())
    <p class="small muted">{{ __('Clients who message your WhatsApp now get an automatic reply in their language, and wait for your call.') }}</p>
    <form method="post" action="{{ route('availability') }}">@csrf<input type="hidden" name="back" value="1"><button class="btn p"><x-icon name="check"/>{{ __('I am back') }}</button></form>
  @else
    <form method="post" action="{{ route('availability') }}" class="form">
      @csrf
      <div class="f2">
        <div class="field"><label for="away_type">{{ __('I will be') }}</label><select id="away_type" name="away_type">@foreach(\App\Models\User::AWAY_TYPES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
        <div class="field"><label for="back_time">{{ __('Back at (today)') }}</label><input type="time" id="back_time" name="back_time" value="14:00"></div>
        <div class="field"><label for="back_date">{{ __('Leave until (date)') }}</label><input type="date" id="back_date" name="back_date" min="{{ today()->format('Y-m-d') }}"></div>
        <div class="field"><label for="backup_user_id">{{ __('Backup for urgent work') }}</label><select id="backup_user_id" name="backup_user_id"><option value="">—</option>@foreach($others as $o)<option value="{{ $o->id }}" @selected($u->backup_user_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
        <div class="field"><label for="salutation">{{ __('Clients should hear my name as') }}</label><select id="salutation" name="salutation">@foreach(['ji' => 'ji', 'sir' => 'Sir', 'madam' => 'Madam'] as $k => $l)<option value="{{ $k }}" @selected($u->salutation === $k)>{{ explode(' ', $u->name)[0] }} {{ $l }}</option>@endforeach</select></div>
      </div>
      <p class="small muted">{{ __('Half day and meeting use the time; leave uses the date.') }}</p>
      <div><button class="btn"><x-icon name="clock"/>{{ __('Set away') }}</button></div>
    </form>
  @endif
</section>
