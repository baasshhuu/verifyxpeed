@extends('layouts.app')
@section('title','Cashfree')
@section('content')
<div class="page-h"><div><h1 class="title">Cashfree & logs 🛰️</h1><p class="sub">Paste your Cashfree keys here, save, then press Test connection. Keys are encrypted; nobody can read them back from this page.</p></div></div>
@if ($ping)<div class="alert {{ $ping['ok'] ? 'ok' : 'err' }}"><span class="e">{{ $ping['ok'] ? '✅' : '❌' }}</span><div><b>{{ ucfirst($ping['env']) }}:</b> {{ $ping['msg'] }}</div></div>@endif
<div class="grid g3 mb">
  @foreach (['sandbox' => '🧪 Secure ID · sandbox', 'production' => '🟢 Secure ID · production'] as $env => $label)
  <div class="card">
    <div class="card-h"><h3>{{ $label }}</h3>@if($cfg[$env])<span class="badge ok">keys added</span>@else<span class="badge warn">Integration Pending</span>@endif</div>
    <p class="muted sm" style="margin-top:-6px">{{ $env === 'sandbox' ? 'Free test environment with Cashfree\'s test data. Used by test API keys.' : 'Real checks. Used by live API keys' }}{{ $cfg['web_env'] === $env ? ' and by the web dashboard right now.' : '.' }}</p>
    <form method="post" action="{{ route('admin.provider.ping') }}">@csrf<input type="hidden" name="env" value="{{ $env }}"><button class="btn {{ $cfg[$env] ? 'btn-lime' : '' }}" type="submit">🔌 Test connection</button></form>
  </div>
  @endforeach
  <div class="card">
    <div class="card-h"><h3>💳 Payment Gateway</h3>@if($cfg['pg'])<span class="badge ok">{{ $cfg['pg_env'] }}</span>@else<span class="badge warn">Integration Pending</span>@endif</div>
    <p class="muted sm" style="margin-top:-6px">Wallet top-ups. Uses your separate Cashfree PG app ID & secret.</p>
  </div>
</div>
@php($mask = fn ($v) => $v ? substr($v, 0, 6) . '••••' . substr($v, -3) : null)
<form method="post" action="{{ route('admin.provider.keys') }}" class="mb">@csrf
<div class="grid g2 mb">
  @foreach (['cf_sandbox' => ['🧪 Secure ID · sandbox (test)', 'sandbox_id', 'sandbox_sig'], 'cf_prod' => ['🟢 Secure ID · production (live)', 'prod_id', 'prod_sig']] as $pre => [$title, $idk, $sigk])
  <div class="card">
    <div class="card-h"><h3>{{ $title }}</h3>@if($cfg[$idk])<span class="badge ok">saved · {{ $mask($cfg[$idk]) }}</span>@else<span class="badge warn">not added</span>@endif</div>
    <p class="hint" style="margin-top:-8px">Cashfree dashboard → Secure ID → Developers → API keys{{ $pre === 'cf_sandbox' ? ' (Test environment)' : ' (Production)' }}.</p>
    <div class="field"><label>Client ID</label><input type="text" name="{{ $pre }}_id" autocomplete="off" placeholder="{{ $cfg[$idk] ? 'saved, leave empty to keep' : 'paste client ID' }}"></div>
    <div class="field"><label>Client secret</label><input type="password" name="{{ $pre }}_secret" autocomplete="new-password" placeholder="{{ $cfg[$idk] ? 'saved, leave empty to keep' : 'paste client secret' }}"></div>
    <div class="field"><label>2FA public key (optional) @if($cfg[$sigk])<span class="badge ok">added</span>@endif</label><textarea name="{{ $pre }}_pubkey" rows="3" placeholder="-----BEGIN PUBLIC KEY----- … only if you chose Public Key instead of IP whitelisting"></textarea></div>
    @if($cfg[$idk])<label class="check"><input type="checkbox" name="{{ $pre }}_clear" value="1"> Remove these saved keys</label>@endif
  </div>
  @endforeach
</div>
<div class="grid g2 mb">
  <div class="card">
    <div class="card-h"><h3>💳 Payment Gateway (top-ups)</h3>@if($cfg['pg'])<span class="badge ok">saved · {{ $mask($cfg['pg_id']) }}</span>@else<span class="badge warn">not added</span>@endif</div>
    <p class="hint" style="margin-top:-8px">Cashfree dashboard → Payment Gateway → Developers → API keys. You can reuse your live Shipxpeed PG account.</p>
    <div class="field"><label>Environment</label><div class="opt-pills">@foreach (['sandbox' => '🧪 Test', 'production' => '🟢 Live'] as $k => $l)<label><input type="radio" name="pg_env" value="{{ $k }}" @checked($cfg['pg_env'] === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
    <div class="form-grid"><div class="field"><label>App ID</label><input type="text" name="pg_app_id" autocomplete="off" placeholder="{{ $cfg['pg'] ? 'saved, leave empty to keep' : 'paste app ID' }}"></div><div class="field"><label>Secret key</label><input type="password" name="pg_secret" autocomplete="new-password" placeholder="{{ $cfg['pg'] ? 'saved, leave empty to keep' : 'paste secret' }}"></div></div>
    @if($cfg['pg'])<label class="check"><input type="checkbox" name="pg_clear" value="1"> Remove saved PG keys</label>@endif
    <div class="field"><label>Bank details shown for bank-transfer top-ups</label><textarea name="bank_details" rows="2">{{ $saved['bank_details'] ?? config('verifyxpeed.topup.bank_details') }}</textarea></div>
  </div>
  <div class="card">
    <div class="card-h"><h3>🎛️ Mode & templates</h3></div>
    <div class="field"><label>Web dashboard runs checks on</label><div class="opt-pills">@foreach (['sandbox' => '🧪 Sandbox (test data, Cashfree test keys)', 'production' => '🟢 Production (real checks)'] as $k => $l)<label><input type="radio" name="web_env" value="{{ $k }}" @checked($cfg['web_env'] === $k)><span>{{ $l }}</span></label>@endforeach</div><span class="hint">API keys choose for themselves: test keys → sandbox, live keys → production.</span></div>
    <div class="field"><label>KYC form link template name</label><input type="text" name="kyc_template" value="{{ $saved['kyc_template'] ?? config('verifyxpeed.cashfree.kyc_link_template') }}"></div>
    <div class="form-grid"><div class="field"><label>Video KYC user template</label><input type="text" name="vkyc_user_template" value="{{ $saved['vkyc_user_template'] ?? config('verifyxpeed.cashfree.vkyc_user_template') }}"></div><div class="field"><label>Video KYC agent template</label><input type="text" name="vkyc_agent_template" value="{{ $saved['vkyc_agent_template'] ?? config('verifyxpeed.cashfree.vkyc_agent_template') }}"></div></div>
  </div>
</div>
<button class="btn btn-lime btn-lg" type="submit">🔐 Save keys</button> <span class="hint">Stored encrypted in the database. Never shown again in full.</span>
</form>
<div class="card mb">
  <h3 style="margin-bottom:12px">🪝 Webhook URLs & IP (add these in Cashfree)</h3>
  <div class="grid g2">
    <div class="field"><label>Secure ID webhook</label><div class="link-box"><code>{{ $cfg['webhook_secureid'] }}</code><button class="btn btn-sm" data-copy="{{ $cfg['webhook_secureid'] }}">Copy</button></div></div>
    <div class="field"><label>Payment Gateway webhook</label><div class="link-box"><code>{{ $cfg['webhook_pg'] }}</code><button class="btn btn-sm" data-copy="{{ $cfg['webhook_pg'] }}">Copy</button></div></div>
  </div>
  <div class="alert info mb0"><span class="e">🧷</span><div>In Cashfree → Secure ID → Developers → Two-Factor Authentication, whitelist this server's public IP @if($serverIp)(server reports <b>{{ $serverIp }}</b>) @endif, or paste the public key above. Without one of these, Cashfree answers 403.</div></div>
</div>
<div class="card"><div class="card-h"><h3>Cashfree API log</h3><span class="muted sm">every call we make</span></div>
@if ($logs->isEmpty())<div class="empty"><span class="e">📡</span>No calls yet.</div>@else
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Time</th><th>Env</th><th>Call</th><th>HTTP</th><th class="r">ms</th><th>Verification</th><th>Error</th></tr></thead><tbody>
@foreach ($logs as $l)<tr><td class="xs muted">{{ $l->created_at->format('d M H:i:s') }}</td><td><span class="badge {{ $l->env === 'production' ? 'ok' : 'warn' }}">{{ $l->env }}</span></td><td class="mono xs">{{ $l->method }} {{ $l->endpoint }}</td><td><span class="badge {{ $l->http_status && $l->http_status < 300 ? 'ok' : 'err' }}">{{ $l->http_status ?: 'conn' }}</span></td><td class="r xs">{{ $l->latency_ms }}</td><td class="mono xs">{{ $l->verification_id }}</td><td class="xs muted" style="white-space:normal;max-width:320px">{{ $l->error_code }} {{ $l->message }}</td></tr>@endforeach
</tbody></table></div>@include('partials.pager', ['p' => $logs])@endif
</div>
@endsection
