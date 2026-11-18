@extends('layouts.app')
@section('title','Wallet')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">Wallet 💰</h1><p class="sub">Prepaid balance for checks on {{ config('verifyxpeed.brand') }} only. Not withdrawable as cash; refunds go back to the original payment method.</p></div></div>
<div class="grid g4 mb">
  <div class="wallet-card span2"><div class="l">Available balance</div><div class="v">{{ $M::inr($wallet->balance_paise) }}</div><div style="opacity:.85;font-size:13.5px;position:relative;z-index:1">Low-balance alert at {{ $M::inr(auth()->user()->low_balance_paise, false) }}</div></div>
  <div class="kpi"><span class="emo">📉</span><div class="l">Spent this month</div><div class="v">{{ $M::inr($stats['spent'], false) }}</div><div class="s">refunds {{ $M::inr($stats['refunds']) }}</div></div>
  <div class="kpi"><span class="emo">📈</span><div class="l">Added this month</div><div class="v">{{ $M::inr($stats['added'], false) }}</div></div>
</div>
<div class="grid g2 mb" id="add">
  <div class="card">
    <div class="card-h"><h3>⚡ Add money instantly</h3>@if($pgReady)<span class="badge {{ $pgEnv === 'production' ? 'ok' : 'warn' }}">Cashfree {{ $pgEnv }}</span>@else<span class="badge warn">Integration Pending</span>@endif</div>
    @if ($pgReady)
    <form method="post" action="{{ route('wallet.topup') }}" data-busy="Opening Cashfree…">@csrf
      <div class="chips row" style="margin-bottom:12px">@foreach ([500, 1000, 2500, 5000, 10000] as $a)<button type="button" class="cat" data-amount="{{ $a }}">{{ $M::inr($a * 100, false) }}</button>@endforeach</div>
      <div class="field"><label>Amount (₹)</label><input id="amount" type="number" name="amount" min="{{ config('verifyxpeed.topup.min') }}" step="1" value="{{ old('amount', 1000) }}" required>@error('amount')<span class="err-text">{{ $message }}</span>@enderror</div>
      <button class="btn btn-lime btn-lg btn-block" type="submit">Pay with UPI / card / net banking →</button>
      <p class="hint center" style="margin:10px 0 0">Secured by Cashfree Payments. Credited the moment payment succeeds.</p>
    </form>
    @else
      <div class="empty" style="padding:20px"><span class="e">🔌</span>Online top-up turns on once the admin adds Cashfree PG keys. Use bank transfer for now →</div>
    @endif
  </div>
  <div class="card">
    <div class="card-h"><h3>🏦 Bank transfer</h3><span class="badge muted">credited after we match it</span></div>
    <div class="alert info" style="white-space:pre-line"><span class="e">🧾</span><div>{{ config('verifyxpeed.topup.bank_details') }}</div></div>
    <form method="post" action="{{ route('wallet.bank') }}" data-busy="Sending…">@csrf
      <div class="form-grid"><div class="field"><label>Amount sent (₹)</label><input type="number" name="amount" min="{{ config('verifyxpeed.topup.min') }}" required></div><div class="field"><label>UTR / reference</label><input type="text" name="utr" required placeholder="e.g. 412345678901"></div></div>
      <button class="btn btn-block" type="submit">I've sent it ✓</button>
    </form>
  </div>
</div>
@if ($topups->isNotEmpty())
<div class="card mb"><div class="card-h"><h3>Recent top-ups</h3></div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Order</th><th>Method</th><th class="r">Amount</th><th>Status</th></tr></thead><tbody>
  @foreach ($topups as $t)<tr><td class="muted sm">{{ $t->created_at->format('d M, h:i a') }}</td><td class="mono">{{ $t->order_id }}</td><td>{{ $t->method === 'pg' ? 'Cashfree' : 'Bank · ' . $t->utr }}</td><td class="r">{{ $M::inr($t->amount_paise) }}</td><td><span class="badge {{ in_array($t->status, ['paid','approved']) ? 'ok' : (in_array($t->status, ['failed','rejected']) ? 'err' : 'warn') }}">{{ str_replace('_', ' ', $t->status) }}</span></td></tr>@endforeach
  </tbody></table></div>
</div>
@endif
<div class="card">
  <div class="card-h"><h3>Ledger</h3><span class="muted sm">every rupee in and out</span></div>
  @if ($txns->isEmpty())<div class="empty"><span class="e">🪙</span>No transactions yet.</div>@else
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Txn ID</th><th>Description</th><th>Type</th><th class="r">Amount</th><th class="r">Balance</th></tr></thead><tbody>
  @foreach ($txns as $t)<tr><td class="muted sm">{{ $t->created_at->format('d M, h:i a') }}</td><td class="mono xs">{{ $t->txn_id }}</td><td>{{ \Illuminate\Support\Str::limit($t->description, 60) }}</td><td><span class="badge {{ ['credit'=>'ok','refund'=>'info','debit'=>'muted','adjustment'=>'warn'][$t->type] ?? 'muted' }}">{{ $t->type }}</span></td><td class="r {{ $t->amount_paise < 0 ? 'neg' : 'pos' }}">{{ $t->amount_paise < 0 ? '−' : '+' }}{{ $M::inr(abs($t->amount_paise)) }}</td><td class="r">{{ $M::inr($t->balance_after_paise) }}</td></tr>@endforeach
  </tbody></table></div>@include('partials.pager', ['p' => $txns])@endif
</div>
@endsection
