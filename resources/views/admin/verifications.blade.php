@extends('layouts.app')
@section('title','All checks')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">All checks 🔍</h1><p class="sub">Every verification on the platform.</p></div></div>
<form method="get" class="card tight mb row">
  <select name="product" style="max-width:240px"><option value="">All products</option>@foreach ($products as $k => $p)<option value="{{ $k }}" @selected(($f['product'] ?? '') === $k)>{{ $p['emoji'] }} {{ $p['name'] }}</option>@endforeach</select>
  <select name="state" style="max-width:180px"><option value="">Any state</option>@foreach (['completed','pending','failed','processing'] as $s)<option @selected(($f['state'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
  <input type="text" name="q" value="{{ $f['q'] ?? '' }}" placeholder="verification ID" style="max-width:240px"><button class="btn btn-lime">Filter</button>
</form>
<div class="card">
@if ($items->isEmpty())<div class="empty"><span class="e">📭</span>Nothing yet.</div>@else
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>When</th><th>Customer</th><th>Check</th><th>Env</th><th>Via</th><th>Result</th><th>Error</th><th class="r">Charged</th><th class="r">Cost</th><th></th></tr></thead><tbody>
@foreach ($items as $v)<tr><td class="xs muted">{{ $v->created_at->format('d M h:i a') }}</td><td class="sm"><a href="{{ route('admin.customer', $v->user) }}" class="b">{{ \Illuminate\Support\Str::limit($v->user->displayName(), 22) }}</a></td><td>{{ $v->meta()['emoji'] ?? '' }} {{ $v->productName() }}</td><td><span class="badge {{ $v->env === 'production' ? 'ok' : 'warn' }}">{{ $v->env }}</span></td><td class="sm">{{ $v->source }}</td><td>@include('partials.vbadge')</td><td class="xs muted">{{ $v->error_code }}</td><td class="r">{{ $M::inr($v->charged_paise) }}</td><td class="r muted">{{ $M::inr($v->state === 'failed' ? 0 : $v->cost_paise) }}</td><td><a class="btn btn-sm" href="{{ route('verifications.show', $v) }}">View</a></td></tr>@endforeach
</tbody></table></div>@include('partials.pager', ['p' => $items])@endif
</div>
@endsection
