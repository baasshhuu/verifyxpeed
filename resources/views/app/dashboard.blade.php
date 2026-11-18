@extends('layouts.app')
@section('title','Home')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h">
  <div><h1 class="title">hey {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }} 👋</h1><p class="sub">Here's what's happening with your checks this month.</p></div>
  <div class="row"><a href="{{ route('bulk') }}" class="btn">📦 Bulk CSV</a><a href="{{ route('checks') }}" class="btn btn-lime">⚡ Run a check</a></div>
</div>

<div class="grid g4 mb">
  <div class="wallet-card span2">
    <div class="l">Wallet balance</div>
    <div class="v">{{ $M::inr($stats['balance']) }}</div>
    <div style="opacity:.85;font-size:13.5px;position:relative;z-index:1">{{ $stats['balance'] < auth()->user()->low_balance_paise ? "wallet's looking thin 👀 top up so checks don't stop" : 'all good, keep verifying ✨' }}</div>
    <div class="chip-row"><a href="{{ route('wallet') }}#add" class="btn btn-white btn-sm">+ Add money</a><a href="{{ route('wallet') }}" class="btn btn-ghost btn-sm" style="border-color:rgba(255,255,255,.4)">Ledger →</a></div>
  </div>
  <div class="kpi"><span class="emo">🔍</span><div class="l">Checks this month</div><div class="v">{{ number_format($stats['month_count']) }}</div><div class="s">{{ $stats['pending'] }} waiting on people</div></div>
  <div class="kpi"><span class="emo">✅</span><div class="l">Verified rate</div><div class="v">{{ $stats['done'] ? round($stats['valid'] / $stats['done'] * 100) : 0 }}%</div><div class="s">spent {{ $M::inr($stats['month_spend']) }}</div></div>
</div>

<div class="grid g2 mb">
  <div class="card">
    <div class="card-h"><h3>Checks per day</h3><span class="badge muted">last 7 days</span></div>
    @php($max = max(1, $days->max('count')))
    <div class="bars">@foreach ($days as $d)<div class="bar-col"><div class="bar" style="height:{{ max(4, $d['count'] / $max * 100) }}%"><em>{{ $d['count'] ?: '' }}</em></div><span>{{ $d['label'] }}</span></div>@endforeach</div>
  </div>
  <div class="card">
    <div class="card-h"><h3>Quick start 🔥</h3><a href="{{ route('checks') }}" class="link sm">see all 30 →</a></div>
    <div class="grid g2">
      @foreach ($popular as $p)
        <a href="{{ route('checks.show', $p['key']) }}" class="row" style="background:var(--bg2);border:1px solid var(--line);border-radius:16px;padding:10px 12px;flex-wrap:nowrap"><div class="blob {{ $p['color'] }}" style="width:40px;height:40px;font-size:20px;border-radius:13px">{{ $p['emoji'] }}</div><div style="min-width:0"><b class="sm" style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $p['name'] }}</b><span class="xs muted">{{ $M::inr($M::withGst($prices[$p['key']]['price_paise'])) }}</span></div></a>
      @endforeach
    </div>
  </div>
</div>

<div class="card">
  <div class="card-h"><h3>Recent checks</h3><a href="{{ route('verifications') }}" class="link sm">history →</a></div>
  @include('app._vtable', ['items' => $recent])
</div>
@endsection
