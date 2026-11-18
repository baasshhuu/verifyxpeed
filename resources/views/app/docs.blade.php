@extends('layouts.app')
@section('title','API docs')
@section('content')
@php($M = \App\Support\Money::class)
@php($prices = \App\Models\ProductSetting::map())
@php($base = url('/api/v1'))
<div class="page-h"><div><h1 class="title">API docs 📖</h1><p class="sub">REST + JSON. Same checks, same wallet, same results as the dashboard.</p></div><a href="{{ route('api-keys') }}" class="btn">🔑 Get a key</a></div>
<div class="grid g3">
  <div class="span2 stack">
    <div class="card">
      <h3 style="margin-bottom:10px">🔐 Auth</h3>
      <p class="muted" style="margin-top:0">Send your key as a Bearer token. <b>Test keys</b> (<code>vx_test_…</code>) use Cashfree's sandbox and are free. <b>Live keys</b> (<code>vx_live_…</code>) hit production and charge your wallet (price + 18% GST).</p>
<pre class="code"><span class="c"># check your balance</span>
curl {{ $base }}/wallet \
  -H <span class="s">"Authorization: Bearer vx_test_xxxxxxxx"</span></pre>
    </div>
    <div class="card">
      <h3 style="margin-bottom:10px">⚡ Run a check</h3>
<pre class="code">POST {{ $base }}/verify/<span class="k">{product}</span>

<span class="c"># JSON body = the product's fields (+ optional "reference": your own id)</span>
curl -X POST {{ $base }}/verify/pan \
  -H <span class="s">"Authorization: Bearer vx_test_xxxxxxxx"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{"pan":"ABCPV1234D","name":"John Doe","reference":"loan-8821"}'</span>

<span class="c"># response</span>
{ "data": {
    "verification_id": "VX01JD…", "product": "pan", "env": "sandbox",
    "state": "completed", "outcome": "valid", "provider_status": "VALID",
    "charged": 0, "refunded": false, "reference": "loan-8821",
    "link": null, "error": null,
    "data": { "registered_name": "JOHN DOE", "pan_status": "VALID", … }
} }</pre>
      <ul class="list-plain sm" style="margin-top:12px">
        <li><code>state</code> → <b>completed</b> · <b>pending</b> (link / OTP flows) · <b>failed</b> (auto-refunded)</li>
        <li><code>outcome</code> → <b>valid</b> or <b>invalid</b> once completed</li>
        <li><code>data</code> → Cashfree's result, unchanged</li>
        <li>Files (face match, OCR, masking, e-sign) → send <code>multipart/form-data</code></li>
      </ul>
    </div>
    <div class="card">
      <h3 style="margin-bottom:10px">🔗 Link & OTP flows</h3>
<pre class="code"><span class="c"># DigiLocker, reverse penny drop, e-sign, KYC link, video KYC:</span>
<span class="c"># 1. create → you get data.link to send the person</span>
<span class="c"># 2. poll until state = completed</span>
POST {{ $base }}/verifications/<span class="k">{verification_id}</span>/refresh

<span class="c"># DigiLocker documents after consent (free)</span>
GET  {{ $base }}/verifications/<span class="k">{verification_id}</span>/documents/AADHAAR

<span class="c"># Mobile 360: create sends OTP, then</span>
POST {{ $base }}/verifications/<span class="k">{verification_id}</span>/otp   { "otp": "111000" }

<span class="c"># fetch any verification</span>
GET  {{ $base }}/verifications/<span class="k">{verification_id}</span>
GET  {{ $base }}/products</pre>
    </div>
    <div class="card">
      <h3 style="margin-bottom:10px">🚨 Errors</h3>
      <table class="kv">
        <tr><td><code>401 unauthorized</code></td><td>Missing / wrong key</td></tr>
        <tr><td><code>402 insufficient_balance</code></td><td>Top up your wallet</td></tr>
        <tr><td><code>403 account_not_approved</code></td><td>Live keys work after business approval</td></tr>
        <tr><td><code>403 ip_not_allowed</code></td><td>Calling IP isn't on the key's whitelist</td></tr>
        <tr><td><code>422 validation_error</code></td><td><code>error.fields</code> lists what's wrong</td></tr>
        <tr><td><code>502 + state=failed</code></td><td>Cashfree couldn't complete it. Refunded automatically</td></tr>
        <tr><td><code>429</code></td><td>Over 120 requests/minute</td></tr>
      </table>
    </div>
    <h2 style="font-size:24px;margin:10px 0 0">All products</h2>
    @foreach (\App\Support\Catalog::all() as $k => $p)
      @continue(!$prices[$k]['enabled'])
      <div class="card" id="{{ $k }}">
        <div class="card-h"><div class="row"><span style="font-size:24px">{{ $p['emoji'] }}</span><h3>{{ $p['name'] }}</h3><span class="badge muted">{{ $p['flow'] }}</span></div><span class="b">{{ $M::inr($M::withGst($prices[$k]['price_paise'])) }} <span class="xs muted">incl. GST</span></span></div>
        <p class="muted sm" style="margin-top:-6px">{{ $p['tagline'] }}</p>
        <code>POST /api/v1/verify/{{ $k }}</code> <span class="badge {{ collect($p['fields'])->contains('type','file') ? 'pink' : 'muted' }}" style="{{ collect($p['fields'])->contains('type','file') ? 'background:rgba(255,78,205,.14);color:var(--pink)' : '' }}">{{ collect($p['fields'])->contains('type','file') ? 'multipart/form-data' : 'application/json' }}</span>
        <table class="kv" style="margin-top:12px">@foreach ($p['fields'] as $f)<tr><td><code>{{ $f['name'] }}{{ $f['type'] === 'multiselect' ? '[]' : '' }}</code> @if(in_array('required', $f['rules'], true))<span class="badge warn">required</span>@endif</td><td>{{ $f['label'] }} <span class="dim">· {{ $f['type'] }}</span>@if(!empty($f['options']))<div class="xs muted">one of: {{ implode(', ', array_keys($f['options'])) }}</div>@endif</td></tr>@endforeach</table>
        @if ($p['test'])<p class="xs muted" style="margin:10px 0 0">🧪 sandbox test input: <code>{{ json_encode($p['test']) }}</code></p>@endif
      </div>
    @endforeach
  </div>
  <div><div class="card tight" style="position:sticky;top:18px;max-height:90vh;overflow:auto"><h3 style="font-size:15px;margin-bottom:8px">Jump to</h3>@foreach (\App\Support\Catalog::all() as $k => $p)<a href="#{{ $k }}" class="row sm" style="padding:5px 0">{{ $p['emoji'] }} {{ $p['name'] }}</a>@endforeach</div></div>
</div>
@endsection
