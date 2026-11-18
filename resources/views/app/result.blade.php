@extends('layouts.app')
@section('title', $p['name'] ?? 'Result')
@section('content')
@php
  $M = \App\Support\Money::class; $P = \App\Support\Present::class;
  $res = $v->response ?? [];
  $data = $res['result'] ?? null;
  $create = $res['create'] ?? [];
  $kind = $v->state === 'failed' ? 'failed' : ($v->state === 'pending' || $v->state === 'processing' ? 'pending' : ($v->outcome === 'valid' ? 'valid' : 'invalid'));
  $copy = [
    'valid' => ['✅', 'Verified ✨', 'The source confirmed it. Details below.'],
    'invalid' => ['🙅', 'Not verified', 'The source checked it and it didn\'t match / doesn\'t exist. This is a real answer, so it\'s charged.'],
    'failed' => ['💥', 'Couldn\'t complete', ($v->error_message ?: 'Cashfree could not complete this call.') . ($v->refunded ? ' You were refunded.' : '')],
    'pending' => ['⏳', $p['flow'] === 'otp' ? 'Waiting for OTP' : 'Waiting on the person', $p['flow'] === 'otp' ? 'Ask the person for the OTP they just received.' : 'Share the link below. We update this when they finish.'],
  ][$kind];
  $link = $v->link();
@endphp
<div class="page-h">
  <div class="row" style="gap:14px;flex-wrap:nowrap"><div class="blob big {{ $p['color'] ?? 'lime' }}">{{ $p['emoji'] ?? '🔍' }}</div><div><h1 class="title">{{ $v->productName() }}</h1><p class="sub mono" style="margin-top:4px">{{ $v->verification_id }}</p></div></div>
  <div class="row"><a href="{{ route('checks.show', $v->product) }}" class="btn">↻ Run another</a><a href="{{ route('verifications') }}" class="btn btn-ghost">History</a></div>
</div>

<div class="verdict {{ $kind }}">
  <div class="big-e">{{ $copy[0] }}</div>
  <div class="grow"><h2>{{ $copy[1] }}</h2><p class="muted" style="margin:4px 0 0">{{ $copy[2] }}</p></div>
  @if ($v->provider_status)<span class="badge muted mono">{{ $v->provider_status }}</span>@endif
</div>

<div class="grid g3">
  <div class="span2 stack">
    @if ($v->state === 'pending' && $p['flow'] === 'link')
      <div class="card">
        <div class="card-h"><h3>🔗 Share this with the person</h3>@if($v->error_code)<span class="badge err">last check: {{ $v->error_code }}</span>@endif</div>
        @if ($link)
          <div class="link-box"><code>{{ $link }}</code><button class="btn btn-sm btn-lime" data-copy="{{ $link }}">Copy</button><a class="btn btn-sm" href="{{ $link }}" target="_blank" rel="noopener">Open ↗</a></div>
        @endif
        @if ($v->product === 'reverse_penny_drop')
          <div class="row" style="margin-top:16px;align-items:flex-start;gap:20px">
            @if (!empty($create['qr_code']))<img class="qr" alt="UPI QR" src="data:image/png;base64,{{ preg_replace('#^data:image/\w+;base64,#', '', $create['qr_code']) }}">@endif
            <div class="stack"><p class="muted sm" style="margin:0">Person scans the QR or taps their UPI app link, pays ₹1, and gets it back. Link valid 10 minutes{{ !empty($create['valid_upto']) ? ' (till ' . $create['valid_upto'] . ')' : '' }}.</p>
              <div class="row">@foreach (['gpay' => 'GPay', 'phonepe' => 'PhonePe', 'paytm' => 'Paytm', 'bhim' => 'BHIM'] as $k => $l)@if(!empty($create[$k]))<button class="btn btn-sm" data-copy="{{ $create[$k] }}">Copy {{ $l }} link</button>@endif @endforeach</div></div>
          </div>
        @elseif ($v->product === 'digilocker')
          <p class="muted sm" style="margin:12px 0 0">The DigiLocker link expires 10 minutes after it's created. If it expires, just create a new one.</p>
        @endif
        <form method="post" action="{{ route('verifications.refresh', $v) }}" style="margin-top:16px" data-busy="Checking…">@csrf<button class="btn btn-lime" type="submit">🔄 Check status now</button></form>
      </div>
    @endif

    @if ($v->state === 'pending' && $p['flow'] === 'otp')
      <div class="card">
        <div class="card-h"><h3>📲 Enter the OTP</h3>@if($v->error_message)<span class="badge err">{{ \Illuminate\Support\Str::limit($v->error_message, 50) }}</span>@endif</div>
        <form method="post" action="{{ route('verifications.otp', $v) }}" class="row" data-busy="Verifying OTP…">@csrf
          <input type="text" name="otp" inputmode="numeric" maxlength="8" placeholder="{{ config('verifyxpeed.web_env') === 'sandbox' ? 'sandbox OTP: 111000' : '6-digit OTP' }}" style="max-width:220px;font-size:20px;letter-spacing:.2em" required>
          <button class="btn btn-lime" type="submit">Verify OTP →</button>
        </form>
        @error('otp')<p class="err-text">{{ $message }}</p>@enderror
      </div>
    @endif

    @if ($data && !empty($p['highlights']))
      <div class="card">
        <div class="card-h"><h3>⭐ Key details</h3>@if($v->env === 'sandbox')<span class="badge warn">SANDBOX data from Cashfree test env</span>@endif</div>
        <div class="hl">@foreach ($p['highlights'] as $h)@if(!is_null(data_get($data, $h)))<div><span>{{ $P::label($h) }}</span><b>{!! $P::value($h, data_get($data, $h)) !!}</b></div>@endif @endforeach</div>
      </div>
    @endif

    @if ($v->product === 'digilocker' && $v->outcome === 'valid')
      <div class="card">
        <div class="card-h"><h3>📄 Documents shared</h3><span class="muted sm">fetching is free</span></div>
        <div class="row">
          @foreach ((array) data_get($data, 'document_consent', data_get($data, 'document_requested', [])) as $doc)
            <form method="post" action="{{ route('verifications.document', [$v, $doc]) }}" data-busy="Fetching…">@csrf<button class="btn {{ isset($res['documents'][$doc]) ? '' : 'btn-lime' }}" type="submit">{{ isset($res['documents'][$doc]) ? '↻' : '⬇️' }} {{ ucwords(strtolower(str_replace('_', ' ', $doc))) }}</button></form>
          @endforeach
        </div>
        @foreach ($res['documents'] ?? [] as $doc => $dd)
          <h4 style="margin:18px 0 6px">{{ ucwords(strtolower(str_replace('_', ' ', $doc))) }}</h4>{!! $P::table($dd) !!}
        @endforeach
      </div>
    @endif

    @if ($data)
      <div class="card"><div class="card-h"><h3>🧾 Full response</h3><button class="btn btn-sm" data-copy='@json($data)'>Copy JSON</button></div>{!! $P::table($data) !!}</div>
    @elseif ($v->state === 'failed')
      <div class="card"><h3 style="margin-bottom:10px">What went wrong</h3>
        <table class="kv"><tr><td>Error code</td><td><code>{{ $v->error_code }}</code></td></tr><tr><td>Message</td><td>{{ $v->error_message }}</td></tr><tr><td>Money</td><td>{{ $v->refunded ? '✅ refunded to wallet' : ($v->charged_paise ? 'charged' : 'not charged') }}</td></tr></table>
        @if (in_array($v->error_code, ['not_configured']))<p class="hint" style="margin-top:12px">The platform's Cashfree keys aren't added yet. The admin needs to add them in <code>.env</code>.</p>@endif
      </div>
    @endif
  </div>

  <div class="stack">
    <div class="card tight">
      <h3 style="font-size:16px;margin-bottom:8px">🧷 What you sent</h3>
      <table class="kv">@foreach ($v->input_masked ?? [] as $k => $val)<tr><td>{{ $P::label($k) }}</td><td>{{ $val }}</td></tr>@endforeach</table>
      <p class="hint" style="margin:10px 0 0">Sensitive numbers are stored masked.</p>
    </div>
    <div class="card tight">
      <h3 style="font-size:16px;margin-bottom:8px">📋 Details</h3>
      <table class="kv">
        <tr><td>Status</td><td>@include('partials.vbadge')</td></tr>
        <tr><td>Charged</td><td>{{ $M::inr($v->charged_paise) }} @if($v->refunded)<span class="badge info">refunded</span>@endif</td></tr>
        <tr><td>Environment</td><td>{{ $v->env }}</td></tr>
        <tr><td>Via</td><td>{{ $v->source }}</td></tr>
        @if($v->client_reference)<tr><td>Your ref</td><td>{{ $v->client_reference }}</td></tr>@endif
        @if($v->reference_id)<tr><td>Cashfree ref</td><td class="mono">{{ $v->reference_id }}</td></tr>@endif
        <tr><td>Created</td><td>{{ $v->created_at->format('d M Y, h:i a') }}</td></tr>
      </table>
    </div>
  </div>
</div>
@endsection
