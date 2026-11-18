@extends('layouts.app')
@section('title','Developers')
@section('content')
<div class="page-h"><div><h1 class="title">Developers 🧑‍💻</h1><p class="sub">Run every check from your own app. Test keys hit Cashfree's sandbox for free; live keys charge your wallet.</p></div><a href="{{ route('docs') }}" class="btn btn-lime">📖 API docs</a></div>
@if (session('new_key'))
  <div class="card mb" style="border-color:var(--lime)"><div class="card-h"><h3>🔑 Your new key</h3><span class="sticker">copy it now!</span></div>
  <div class="link-box"><code>{{ session('new_key') }}</code><button class="btn btn-sm btn-lime" data-copy="{{ session('new_key') }}">Copy</button></div>
  <p class="hint" style="margin:10px 0 0">We only store a hash. If you lose it, revoke it and make a new one.</p></div>
@endif
<div class="grid g3">
  <div class="card span2">
    <div class="card-h"><h3>API keys</h3></div>
    @if ($keys->isEmpty())<div class="empty"><span class="e">🗝️</span>No keys yet. Make a test key and start building →</div>@else
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Key</th><th>Mode</th><th>IPs</th><th>Last used</th><th></th></tr></thead><tbody>
    @foreach ($keys as $k)<tr style="{{ $k->revoked_at ? 'opacity:.45' : '' }}"><td><b>{{ $k->name }}</b></td><td class="mono">{{ $k->prefix }}••••</td><td><span class="badge {{ $k->mode === 'live' ? 'ok' : 'warn' }}">{{ $k->mode }}</span></td><td class="xs muted">{{ $k->ip_whitelist ? implode(', ', $k->ip_whitelist) : 'any' }}</td><td class="muted sm">{{ $k->revoked_at ? 'revoked' : ($k->last_used_at?->diffForHumans() ?? 'never') }}</td><td>@unless($k->revoked_at)<form method="post" action="{{ route('api-keys.destroy', $k) }}" onsubmit="return confirm('Revoke this key? Apps using it will stop working.')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Revoke</button></form>@endunless</td></tr>@endforeach
    </tbody></table></div>@endif
  </div>
  <div class="card">
    <h3 style="font-size:17px;margin-bottom:14px">✨ New key</h3>
    <form method="post" action="{{ route('api-keys.store') }}">@csrf
      <div class="field"><label>Name</label><input type="text" name="name" placeholder="e.g. Loan app backend" required></div>
      <div class="field"><label>Mode</label><div class="opt-pills"><label><input type="radio" name="mode" value="test" checked><span>🧪 Test</span></label><label><input type="radio" name="mode" value="live"><span>🟢 Live</span></label></div></div>
      <div class="field"><label>Allowed IPs (optional)</label><input type="text" name="ips" placeholder="203.0.113.10, 198.51.100.7"><span class="hint">Leave empty to allow any IP.</span></div>
      <button class="btn btn-lime btn-block" type="submit">Create key</button>
    </form>
  </div>
</div>
@endsection
