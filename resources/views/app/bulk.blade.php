@extends('layouts.app')
@section('title','Bulk CSV')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">Bulk CSV 📦</h1><p class="sub">Drop a CSV, pick a check, we run every row and give you a results file. Charged per row processed.</p></div></div>
<div class="grid g3 mb">
  <div class="card span2">
    <form method="post" action="{{ route('bulk.store') }}" enctype="multipart/form-data" data-busy="Uploading…">@csrf
      <div class="field"><label>Which check?</label>
        <select name="product" id="bulkProduct" required>@foreach ($products as $k => $p)<option value="{{ $k }}" @selected(old('product') === $k)>{{ $p['emoji'] }} {{ $p['name'] }} · {{ $M::inr($M::withGst($prices[$k]['price_paise'])) }}/row</option>@endforeach</select></div>
      <div class="field"><label>CSV file</label><label class="drop"><input type="file" name="file" accept=".csv,text/csv" required><span class="e">📄</span><b>Drop your CSV here</b><span class="hint">max {{ number_format(config('verifyxpeed.bulk_max_rows')) }} rows · 5 MB</span><span class="fname"></span></label>@error('file')<span class="err-text">{{ $message }}</span>@enderror</div>
      <label class="check"><input type="checkbox" name="consent" value="1"> Consent was collected from everyone in this file, for a lawful purpose.</label>
      @error('consent')<p class="err-text" style="margin-top:-10px">{{ $message }}</p>@enderror
      <button class="btn btn-lime btn-lg" type="submit" @disabled($block)>🚀 Start bulk job</button>
    </form>
  </div>
  <div class="card">
    <h3 style="font-size:17px;margin-bottom:10px">How the CSV looks</h3>
    <ol class="steps"><li>First row = column names (the field names below)</li><li>One person per row</li><li>Optional <code>reference</code> column to match results back</li></ol>
    <div class="divider"></div>
    @foreach ($products as $k => $p)<div class="row" style="padding:6px 0"><span class="sm">{{ $p['emoji'] }} <code>{{ implode(', ', array_column($p['fields'], 'name')) }}</code></span><a class="link xs right" href="{{ route('bulk.sample', $k) }}">sample</a></div>@endforeach
  </div>
</div>
<div class="card"><div class="card-h"><h3>Your bulk jobs</h3></div>
@if ($jobs->isEmpty())<div class="empty"><span class="e">🗂️</span>No bulk jobs yet.</div>@else
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>File</th><th>Check</th><th>Progress</th><th>✅</th><th>🙅</th><th>💥</th><th>Status</th><th></th></tr></thead><tbody>
@foreach ($jobs as $j)<tr><td><b>{{ \Illuminate\Support\Str::limit($j->file_name, 28) }}</b><div class="xs muted">{{ $j->created_at->diffForHumans() }}</div></td><td>{{ $j->productName() }}</td><td style="min-width:140px"><div class="progress"><div style="width:{{ $j->percent() }}%"></div></div><span class="xs muted">{{ $j->processed }}/{{ $j->total }}</span></td><td class="pos">{{ $j->valid }}</td><td>{{ $j->invalid }}</td><td class="neg">{{ $j->failed }}</td><td><span class="badge {{ ['done'=>'ok','running'=>'info','queued'=>'warn','stopped'=>'err'][$j->status] }}">{{ $j->status }}</span></td><td><a class="btn btn-sm" href="{{ route('bulk.show', $j) }}">Open</a></td></tr>@endforeach
</tbody></table></div>@endif
</div>
@endsection
