<div class="toasts">
  @if (session('ok'))<div class="toast">{{ session('ok') }}</div>@endif
  @if (session('err'))<div class="toast err">{{ session('err') }}</div>@endif
</div>
