@extends('layouts.base')
@section('body')
@php
  $u = auth()->user();
  $admin = request()->routeIs('admin.*');
  $bal = $u->walletModel()->balance_paise;
  $env = config('verifyxpeed.web_env');
  $pendingKyb = $admin ? \App\Models\User::where('role','customer')->where('kyb_status','pending')->count() : 0;
  $pendingTop = $admin ? \App\Models\Topup::where('status','pending_approval')->count() : 0;
  $nav = $admin ? [
    ['admin.dashboard','🏠','Overview'], ['sec','Customers'], ['admin.customers','👥','Customers', $pendingKyb], ['admin.verifications','🔍','All checks'], ['admin.topups','💸','Top-ups', $pendingTop],
    ['sec','Setup'], ['admin.pricing','🏷️','Pricing & margins'], ['admin.provider','🛰️','Cashfree & logs'],
  ] : [
    ['dashboard','🏠','Home'], ['checks','⚡','Run a check'], ['verifications','📚','History'], ['bulk','📦','Bulk CSV'],
    ['sec','Money & code'], ['wallet','💰','Wallet'], ['api-keys','🧑‍💻','Developers'], ['settings','⚙️','Settings'],
  ];
@endphp
<div class="shell">
  <aside class="side">
    <div class="side-in">
      <a class="brand" href="{{ route($admin ? 'admin.dashboard' : 'dashboard') }}"><div class="logo">VX</div><div><b>{{ config('verifyxpeed.brand') }}</b><small>{{ $admin ? 'admin HQ 🛡️' : 'verify anyone, fast ⚡' }}</small></div></a>
      <nav class="nav">
        @foreach ($nav as $n)
          @if ($n[0] === 'sec')<div class="nav-sec">{{ $n[1] }}</div>
          @else<a href="{{ route($n[0]) }}" class="{{ (request()->routeIs($n[0]) || request()->routeIs($n[0].'.*') || ($n[0]==='admin.customers' && request()->routeIs('admin.customer*'))) ? 'on' : '' }}"><span class="e">{{ $n[1] }}</span>{{ $n[2] }}@if(!empty($n[3]))<span class="count">{{ $n[3] }}</span>@endif</a>@endif
        @endforeach
        @if ($u->isAdmin())
          <div class="nav-sec">Switch</div>
          @if ($admin)<a href="{{ route('dashboard') }}"><span class="e">🧑</span>Customer view</a>@else<a href="{{ route('admin.dashboard') }}"><span class="e">🛡️</span>Admin panel</a>@endif
        @endif
      </nav>
      <div class="side-foot">
        <div class="me"><div class="avatar">{{ strtoupper(mb_substr($u->displayName(),0,2)) }}</div><div class="grow"><b>{{ $u->displayName() }}</b><small>{{ $u->email }}</small></div></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm btn-block" style="margin-top:8px">Log out 👋</button></form>
      </div>
    </div>
  </aside>
  <main class="main">
    <div class="top">
      <button class="btn btn-sm menu-btn" data-menu>☰</button>
      <span class="env-pill {{ $env }}">{{ $env === 'sandbox' ? '🧪 sandbox mode' : '🟢 live' }}</span>
      <div class="grow"></div>
      @unless ($admin)
        <a href="{{ route('wallet') }}" class="wallet-chip {{ $bal < $u->low_balance_paise ? 'low' : '' }}"><span class="coin">₹</span>{{ \App\Support\Money::inr($bal) }}</a>
        <a href="{{ route('checks') }}" class="btn btn-lime btn-sm">⚡ New check</a>
      @endunless
    </div>
    @if (!$admin && ($why = $u->verifyBlockReason()))
      <div class="alert warn"><span class="e">⏳</span><div><b>Almost there.</b> {{ $why }}</div></div>
    @endif
    @yield('content')
  </main>
</div>
@endsection
