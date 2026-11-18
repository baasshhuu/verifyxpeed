@extends('layouts.base')
@section('body')
<div class="auth">
  <div class="auth-art">
    <a class="brand" href="{{ route('home') }}"><div class="logo">VX</div><div><b>{{ config('verifyxpeed.brand') }}</b><small>verify anyone, fast ⚡</small></div></a>
    <span class="floaty" style="top:18%;right:12%">🪪</span><span class="floaty" style="top:46%;right:30%;animation-delay:1.5s">🏦</span><span class="floaty" style="bottom:22%;right:10%;animation-delay:3s">🤳</span>
    <div>
      <span class="sticker">30+ checks · one wallet</span>
      <h1 style="margin-top:18px">KYC that <span class="hl-lime">doesn't</span> make people wait.</h1>
      <p class="muted" style="font-size:17px;max-width:460px">PAN, Aadhaar via DigiLocker, bank accounts, GST, faces, vehicles… top up once, verify anything in seconds. From the dashboard or our API.</p>
    </div>
    <div class="marquee"><span>🪪 PAN</span><span>🔐 DigiLocker</span><span>🏦 Penny drop</span><span>💸 UPI</span><span>🧾 GSTIN</span><span>🤳 Face match</span><span>🏍️ Vehicle RC</span><span>✍️ e-Sign</span></div>
  </div>
  <div class="auth-form"><div class="auth-card">@yield('content')</div></div>
</div>
@endsection
