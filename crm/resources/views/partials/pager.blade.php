@if($p->lastPage() > 1)
<nav class="pager" aria-label="{{ __('Pages') }}">
  @if($p->previousPageUrl())<a href="{{ $p->previousPageUrl() }}">‹ {{ __('Previous') }}</a>@endif
  <span>{{ __('Page :n of :m', ['n' => $p->currentPage(), 'm' => $p->lastPage()]) }}</span>
  @if($p->nextPageUrl())<a href="{{ $p->nextPageUrl() }}">{{ __('Next') }} ›</a>@endif
</nav>
@endif
