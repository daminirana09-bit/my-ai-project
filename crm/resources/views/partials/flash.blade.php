@if(session('ok'))<div class="flash ok" role="status">{{ session('ok') }}</div>@endif
@if(session('warn'))<div class="flash warn" role="status">{{ session('warn') }}</div>@endif
@if($errors->any())<div class="flash bad" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
