@extends('layouts.app')
@section('title', $u->displayName())
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">{{ $u->displayName() }}</h1><p class="sub">{{ $u->name }} · {{ $u->email }} · {{ $u->phone }}</p></div><a href="{{ route('admin.customers') }}" class="btn btn-ghost">← Customers</a></div>
<div class="grid g3 mb">
  <div class="card span2">
    <div class="card-h"><h3>Business application</h3><span class="badge {{ ['approved'=>'ok','pending'=>'warn','rejected'=>'err'][$u->kyb_status] }}">KYB {{ $u->kyb_status }}</span></div>
    <table class="kv">
      <tr><td>Business type</td><td>{{ $u->business_type ?: '—' }}</td></tr>
      <tr><td>GSTIN</td><td class="mono">{{ $u->gstin ?: '—' }} @if($u->gstin)<a class="link xs" href="{{ route('checks.show', 'gstin') }}">verify it →</a>@endif</td></tr>
      <tr><td>Stated purpose</td><td>{{ $u->use_case ?: '—' }}</td></tr>
      <tr><td>Signed up</td><td>{{ $u->created_at->format('d M Y, h:i a') }}</td></tr>
      @if($u->approved_at)<tr><td>Approved</td><td>{{ $u->approved_at->format('d M Y') }}</td></tr>@endif
    </table>
    <div class="row" style="margin-top:16px">
      @if ($u->kyb_status !== 'approved')<form method="post" action="{{ route('admin.customer.kyb', $u) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-lime">✅ Approve</button></form>@endif
      @if ($u->kyb_status !== 'rejected')<form method="post" action="{{ route('admin.customer.kyb', $u) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn btn-danger">🚫 Reject</button></form>@endif
      <form method="post" action="{{ route('admin.customer.status', $u) }}" onsubmit="return confirm('Change account status?')">@csrf<button class="btn btn-ghost">{{ $u->status === 'suspended' ? '▶️ Reactivate' : '⏸️ Suspend' }}</button></form>
    </div>
  </div>
  <div class="card">
    <div class="card-h"><h3>Wallet</h3><b class="lime" style="font-size:20px">{{ $M::inr($u->wallet?->balance_paise ?? 0) }}</b></div>
    <form method="post" action="{{ route('admin.customer.wallet', $u) }}" onsubmit="return confirm('Apply this wallet adjustment?')">@csrf
      <div class="field"><div class="opt-pills"><label><input type="radio" name="type" value="credit" checked><span>➕ Credit</span></label><label><input type="radio" name="type" value="debit"><span>➖ Debit</span></label></div></div>
      <div class="field"><label>Amount (₹)</label><input type="number" name="amount" min="1" step="0.01" required></div>
      <div class="field"><label>Reason (customer sees this)</label><input type="text" name="reason" required placeholder="e.g. Goodwill credit"></div>
      <button class="btn btn-block">Apply</button>
    </form>
  </div>
</div>
<div class="grid g2">
  <div class="card"><div class="card-h"><h3>Recent checks</h3></div>@include('app._vtable', ['items' => $recent])</div>
  <div class="card"><div class="card-h"><h3>Ledger</h3></div>
    <div class="tbl-wrap"><table class="tbl"><tbody>@forelse ($txns as $t)<tr><td class="xs muted">{{ $t->created_at->format('d M h:i a') }}</td><td class="sm">{{ \Illuminate\Support\Str::limit($t->description, 40) }}</td><td class="r {{ $t->amount_paise < 0 ? 'neg' : 'pos' }}">{{ $t->amount_paise < 0 ? '−' : '+' }}{{ $M::inr(abs($t->amount_paise)) }}</td></tr>@empty<tr><td class="muted">No transactions.</td></tr>@endforelse</tbody></table></div>
    @if ($keys->isNotEmpty())<div class="divider"></div><h4 style="margin-bottom:8px">API keys</h4>@foreach ($keys as $k)<div class="row sm" style="padding:4px 0"><span class="mono">{{ $k->prefix }}••</span><span class="badge {{ $k->mode === 'live' ? 'ok' : 'warn' }}">{{ $k->mode }}</span><span class="muted right">{{ $k->revoked_at ? 'revoked' : ($k->last_used_at?->diffForHumans() ?? 'unused') }}</span></div>@endforeach @endif
  </div>
</div>
@endsection
