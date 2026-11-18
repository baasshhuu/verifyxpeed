@extends('layouts.app')
@section('title','Admin')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">Admin HQ 🛡️</h1><p class="sub">This month across every customer. Revenue shown before GST.</p></div><a href="{{ route('admin.provider') }}" class="btn">🛰️ Cashfree status</a></div>
@if (!$env['sandbox'] && !$env['production'])<div class="alert warn"><span class="e">🔌</span><div><b>Cashfree Secure ID keys are missing.</b> Every check will fail and refund until you add them to <code>.env</code>. See <a class="link" href="{{ route('admin.provider') }}">Cashfree & logs</a>.</div></div>@endif
@if ($env['web'] === 'production' && !$env['production'])<div class="alert err"><span class="e">🚨</span><div>Web is set to <b>production</b> but production keys are missing.</div></div>@endif
<div class="grid g4 mb">
  <div class="kpi lime"><div class="l">Revenue (ex-GST)</div><div class="v">{{ $M::inr($stats['revenue'], false) }}</div><div class="s">this month</div></div>
  <div class="kpi"><span class="emo">🧾</span><div class="l">Cashfree cost</div><div class="v">{{ $M::inr($stats['cost'], false) }}</div></div>
  <div class="kpi"><span class="emo">📈</span><div class="l">Gross margin</div><div class="v">{{ $M::inr($stats['margin'], false) }}</div><div class="s">{{ $stats['revenue'] ? round($stats['margin'] / $stats['revenue'] * 100) : 0 }}% of revenue</div></div>
  <div class="kpi"><span class="emo">👛</span><div class="l">Customer wallets</div><div class="v">{{ $M::inr($stats['balances'], false) }}</div><div class="s">money you hold for customers</div></div>
</div>
<div class="grid g4 mb">
  <a href="{{ route('admin.customers', ['kyb' => 'pending']) }}" class="kpi"><span class="emo">⏳</span><div class="l">Waiting for approval</div><div class="v">{{ $stats['pending_kyb'] }}</div><div class="s">of {{ $stats['customers'] }} customers</div></a>
  <a href="{{ route('admin.topups', ['status' => 'pending_approval']) }}" class="kpi"><span class="emo">🏦</span><div class="l">Bank top-ups to match</div><div class="v">{{ $stats['pending_topups'] }}</div></a>
  <div class="kpi"><span class="emo">🔍</span><div class="l">Checks today</div><div class="v">{{ number_format($stats['today']) }}</div></div>
  <div class="kpi"><span class="emo">💥</span><div class="l">Failed today</div><div class="v">{{ $stats['fail_today'] }}</div><div class="s">auto-refunded</div></div>
</div>
<div class="grid g2 mb">
  <div class="card"><div class="card-h"><h3>Checks per day</h3></div>@php($max = max(1, $days->max('count')))<div class="bars">@foreach ($days as $d)<div class="bar-col"><div class="bar" style="height:{{ max(4, $d['count'] / $max * 100) }}%"><em>{{ $d['count'] ?: '' }}</em></div><span>{{ $d['label'] }}</span></div>@endforeach</div></div>
  <div class="card"><div class="card-h"><h3>Top products</h3><a class="link sm" href="{{ route('admin.pricing') }}">pricing →</a></div>
    @if ($mix->isEmpty())<div class="empty"><span class="e">📭</span>No checks this month yet.</div>@else
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Check</th><th class="r">Count</th><th class="r">Revenue</th></tr></thead><tbody>@foreach ($mix as $m)<tr><td>{{ \App\Support\Catalog::get($m->product)['emoji'] ?? '' }} {{ \App\Support\Catalog::get($m->product)['name'] ?? $m->product }}</td><td class="r">{{ $m->c }}</td><td class="r">{{ $M::inr($m->rev) }}</td></tr>@endforeach</tbody></table></div>@endif
  </div>
</div>
<div class="card"><div class="card-h"><h3>Low-balance customers 👀</h3></div>
  @if ($low->isEmpty())<div class="empty"><span class="e">😌</span>Everyone's topped up.</div>@else
  <div class="tbl-wrap"><table class="tbl"><tbody>@foreach ($low as $u)<tr><td><a class="b" href="{{ route('admin.customer', $u) }}">{{ $u->displayName() }}</a></td><td class="muted">{{ $u->email }}</td><td class="r">{{ $M::inr($u->wallet->balance_paise) }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
@endsection
