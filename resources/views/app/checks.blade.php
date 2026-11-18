@extends('layouts.app')
@section('title','Run a check')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h">
  <div><h1 class="title">What are we <span class="em">verifying</span> today?</h1><p class="sub">Pick a check. You only pay when it runs, and failed calls are refunded automatically.</p></div>
  <form method="get" class="row"><input type="text" name="q" value="{{ $q }}" placeholder="🔎 search checks…" style="width:240px">@if($cat)<input type="hidden" name="cat" value="{{ $cat }}">@endif</form>
</div>
<div class="cats">
  <a href="{{ route('checks') }}" class="cat {{ !$cat ? 'on' : '' }}">✨ All</a>
  @foreach (\App\Support\Catalog::CATEGORIES as $k => $c)<a href="{{ route('checks', ['cat' => $k]) }}" class="cat {{ $cat === $k ? 'on' : '' }}">{{ $c['emoji'] }} {{ $c['label'] }}</a>@endforeach
</div>
@if ($products->isEmpty())<div class="card empty"><span class="e">🤷</span>Nothing matches that. Try another word.</div>@endif
<div class="grid g-auto">
@foreach ($products as $p)
  <a href="{{ route('checks.show', $p['key']) }}" class="tile">
    @if ($p['flow'] !== 'sync')<span class="flow badge info">{{ $p['flow'] === 'otp' ? '📲 OTP' : '🔗 link' }}</span>@endif
    <div class="blob {{ $p['color'] }}">{{ $p['emoji'] }}</div>
    <h3>{{ $p['name'] }}</h3>
    <p>{{ $p['tagline'] }}</p>
    <div class="row"><span class="price">{{ $M::inr($prices[$p['key']]['price_paise']) }} <small>+ GST</small></span><span class="right link sm">run →</span></div>
  </a>
@endforeach
</div>
@endsection
