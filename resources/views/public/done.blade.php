@extends('layouts.base')
@section('title','All done')
@section('body')
<div style="min-height:100vh;display:grid;place-items:center;padding:20px">
  <div class="card center" style="max-width:440px;padding:40px">
    <div style="font-size:64px">🎉</div>
    <h1 class="title" style="font-size:30px;margin-top:6px">All done!</h1>
    <p class="muted">Thanks, your verification has been submitted. You can close this page now.</p>
    <span class="badge muted mono" style="margin-top:6px">{{ $v?->verification_id ?? 'ref unavailable' }}</span>
    <p class="xs dim" style="margin-top:20px">Powered by {{ config('verifyxpeed.brand') }}</p>
  </div>
</div>
@endsection
