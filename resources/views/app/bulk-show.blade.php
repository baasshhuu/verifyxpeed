@extends('layouts.app')
@section('title','Bulk job')
@push('head')@if(in_array($job->status, ['queued','running']))<meta http-equiv="refresh" content="4">@endif @endpush
@section('content')
<div class="page-h"><div><h1 class="title">{{ $job->file_name }}</h1><p class="sub">{{ $job->productName() }} · {{ $job->total }} rows · started {{ $job->created_at->diffForHumans() }}</p></div>
<div class="row">@if($job->status === 'stopped')<form method="post" action="{{ route('bulk.resume', $job) }}">@csrf<button class="btn btn-lime">▶️ Resume</button></form>@endif<a class="btn {{ $job->status === 'done' ? 'btn-lime' : '' }}" href="{{ route('bulk.download', $job) }}">⬇️ Results CSV</a><a class="btn btn-ghost" href="{{ route('bulk') }}">← Bulk</a></div></div>
@if ($job->note)<div class="alert warn"><span class="e">⚠️</span><div>{{ $job->note }}</div></div>@endif
<div class="grid g4 mb">
  <div class="kpi lime"><div class="l">Progress</div><div class="v">{{ $job->percent() }}%</div><div class="s">{{ $job->processed }} of {{ $job->total }}</div></div>
  <div class="kpi"><span class="emo">✅</span><div class="l">Verified</div><div class="v">{{ $job->valid }}</div></div>
  <div class="kpi"><span class="emo">🙅</span><div class="l">Not verified</div><div class="v">{{ $job->invalid }}</div></div>
  <div class="kpi"><span class="emo">💥</span><div class="l">Failed / bad rows</div><div class="v">{{ $job->failed }}</div><div class="s">failed calls are refunded</div></div>
</div>
<div class="card mb"><div class="progress"><div style="width:{{ $job->percent() }}%"></div></div><p class="hint" style="margin:8px 0 0">{{ in_array($job->status, ['queued','running']) ? 'Working on it… this page refreshes itself 🔄' : 'Status: ' . $job->status }}</p></div>
<div class="card">@include('app._vtable', ['items' => $items])@include('partials.pager', ['p' => $items])</div>
@endsection
