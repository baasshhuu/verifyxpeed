@if ($p->hasPages())
<div class="pager">
  @if ($p->onFirstPage())<span class="off">← Prev</span>@else<a href="{{ $p->previousPageUrl() }}">← Prev</a>@endif
  <span class="on">{{ $p->currentPage() }} / {{ $p->lastPage() }}</span>
  @if ($p->hasMorePages())<a href="{{ $p->nextPageUrl() }}">Next →</a>@else<span class="off">Next →</span>@endif
</div>
@endif
