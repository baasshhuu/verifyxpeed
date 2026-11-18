@extends('layouts.base')
@section('title','KYC & verification APIs')
@section('body')
<div class="hero">
  <div class="hero-nav">
    <a class="brand" href="/"><div class="logo">VX</div><div><b>{{ config('verifyxpeed.brand') }}</b><small>verify anyone, fast ⚡</small></div></a>
    <div class="row"><a href="{{ route('login') }}" class="btn btn-ghost">Log in</a><a href="{{ route('register') }}" class="btn btn-lime">Get started →</a></div>
  </div>
  <span class="sticker">no monthly fee · pay per check</span>
  <h1 style="margin-top:20px">Verify anyone.<br><span style="color:var(--lime)">In seconds.</span> 🪄</h1>
  <p class="lead">PAN, Aadhaar via DigiLocker, bank accounts, UPI, GST, faces, vehicles and 20+ more checks. Top up a wallet once, then run checks from the dashboard, a CSV, or our API.</p>
  <div class="row"><a href="{{ route('register') }}" class="btn btn-lime btn-lg">Create free account 🚀</a><a href="{{ route('login') }}" class="btn btn-lg">I have an account</a></div>
  <div class="grid g-auto" style="margin-top:56px">
    @foreach (collect(\App\Support\Catalog::all())->take(8) as $p)
      <div class="tile"><div class="blob {{ $p['color'] }}">{{ $p['emoji'] }}</div><h3>{{ $p['name'] }}</h3><p>{{ $p['tagline'] }}</p></div>
    @endforeach
  </div>
</div>
@endsection
