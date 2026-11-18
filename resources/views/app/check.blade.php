@extends('layouts.app')
@section('title', $p['name'])
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h">
  <div class="row" style="gap:16px;flex-wrap:nowrap"><div class="blob big {{ $p['color'] }}">{{ $p['emoji'] }}</div><div><h1 class="title">{{ $p['name'] }}</h1><p class="sub" style="margin-top:4px">{{ $p['tagline'] }}</p></div></div>
  <a href="{{ route('checks') }}" class="btn btn-ghost">← All checks</a>
</div>
<div class="grid g3">
  <div class="card span2">
    @if ($p['flow'] === 'link')<div class="alert info"><span class="e">🔗</span><div>This creates a <b>link</b> for the person to open. The result shows up here once they finish (we check Cashfree automatically, or press "Check status").</div></div>@endif
    @if ($p['flow'] === 'otp')<div class="alert info"><span class="e">📲</span><div>Step 1 sends an OTP to the person's phone. Step 2: enter the OTP they read out to you.</div></div>@endif
    @if ($p['key'] === 'vkyc' && !config('verifyxpeed.cashfree.vkyc_user_template'))<div class="alert warn"><span class="e">⚠️</span><div>Video KYC needs user & agent templates created in your Cashfree dashboard. Ask the admin to add them before using this.</div></div>@endif
    <form method="post" action="{{ route('checks.run', $p['key']) }}" enctype="multipart/form-data" data-busy="Verifying…">@csrf
      <div class="form-grid">
      @foreach ($p['fields'] as $f)
        @php($n = $f['name'])
        <div class="field" @if(in_array($f['type'], ['file','textarea','multiselect','checkbox']) ) style="grid-column:1/-1" @endif>
          @if ($f['type'] !== 'checkbox')<label>{{ $f['label'] }}</label>@endif
          @switch($f['type'])
            @case('select')
              <select name="{{ $n }}">@foreach ($f['options'] as $ov => $ol)<option value="{{ $ov }}" @selected(old($n) == $ov)>{{ $ol }}</option>@endforeach</select>@break
            @case('multiselect')
              <div class="opt-pills">@foreach ($f['options'] as $ov => $ol)<label><input type="checkbox" name="{{ $n }}[]" value="{{ $ov }}" @checked(in_array($ov, old($n, $f['default'] ?? [])))><span>{{ $ol }}</span></label>@endforeach</div>@break
            @case('textarea')
              <textarea name="{{ $n }}" placeholder="{{ $f['placeholder'] ?? '' }}">{{ old($n) }}</textarea>@break
            @case('file')
              <label class="drop"><input type="file" name="{{ $n }}" accept="{{ $f['accept'] ?? '' }}"><span class="e">📤</span><b>Drop or pick a file</b><span class="hint">{{ str_contains($f['accept'] ?? '', 'pdf') ? 'JPG, PNG or PDF' : 'JPG or PNG' }}</span><span class="fname"></span></label>@break
            @case('checkbox')
              <label class="check"><input type="checkbox" name="{{ $n }}" value="1" @checked(old($n))> {{ $f['label'] }}</label>@break
            @case('date')
              <input type="date" name="{{ $n }}" value="{{ old($n) }}">@break
            @default
              <input type="text" name="{{ $n }}" value="{{ old($n, $f['default'] ?? '') }}" placeholder="{{ $f['placeholder'] ?? '' }}" @if(!empty($f['upper'])) style="text-transform:uppercase" @endif>
          @endswitch
          @error($n)<span class="err-text">{{ $message }}</span>@enderror
          @error($n.'.*')<span class="err-text">{{ $message }}</span>@enderror
        </div>
      @endforeach
      </div>
      @if (!empty($p['hint']))<p class="hint" style="margin-top:-6px">{{ $p['hint'] }}</p>@endif
      <label class="check"><input type="checkbox" name="consent" value="1" @checked(old('consent'))> The person has given consent for this check, for a lawful purpose (DPDP Act 2023).</label>
      @error('consent')<p class="err-text" style="margin-top:-10px">{{ $message }}</p>@enderror
      <div class="row">
        <button type="submit" class="btn btn-lime btn-lg" @disabled($block)>{{ $p['flow'] === 'link' ? '🔗 Create link' : ($p['flow'] === 'otp' ? '📲 Send OTP' : '⚡ Verify') }} · {{ $M::inr($M::withGst($price['price_paise'])) }}</button>
        @if ($sandbox && $p['test'])<button type="button" class="try" data-fill='@json($p['test'])'>🧪 fill sandbox test data</button>@endif
      </div>
    </form>
  </div>
  <div class="stack">
    <div class="card tight">
      <h3 style="font-size:16px;margin-bottom:10px">💳 Price</h3>
      <div class="row"><span class="muted">Check</span><b class="right">{{ $M::inr($price['price_paise']) }}</b></div>
      <div class="row" style="margin-top:4px"><span class="muted">GST 18%</span><b class="right">{{ $M::inr($M::withGst($price['price_paise']) - $price['price_paise']) }}</b></div>
      <div class="divider" style="margin:10px 0"></div>
      <div class="row"><b>You pay</b><b class="right lime" style="font-size:18px">{{ $M::inr($M::withGst($price['price_paise'])) }}</b></div>
      <p class="hint" style="margin:10px 0 0">Charged from your wallet. Auto-refunded if Cashfree can't complete the call.</p>
    </div>
    <div class="card tight">
      <h3 style="font-size:16px;margin-bottom:6px">🕘 Recent</h3>
      @forelse ($recent as $v)
        <a href="{{ route('verifications.show', $v) }}" class="row" style="padding:8px 0;border-top:1px solid var(--line)"><span class="sm muted">{{ \Illuminate\Support\Str::limit(collect($v->input_masked)->first() ?? '—', 18) }}</span><span class="right">@include('partials.vbadge')</span></a>
      @empty <p class="muted sm" style="margin:0">Nothing yet.</p>
      @endforelse
    </div>
    <div class="card tight"><h3 style="font-size:16px;margin-bottom:6px">🧑‍💻 Via API</h3><code class="xs" style="word-break:break-all">POST /api/v1/verify/{{ $p['key'] }}</code><p class="hint" style="margin:8px 0 0"><a class="link" href="{{ route('docs') }}#{{ $p['key'] }}">See docs →</a></p></div>
  </div>
</div>
@endsection
