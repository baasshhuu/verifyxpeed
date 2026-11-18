@extends('layouts.app')
@section('title','History')
@section('content')
<div class="page-h">
  <div><h1 class="title">History 📚</h1><p class="sub">Every check you've run. Sensitive inputs are masked.</p></div>
  <a href="{{ route('verifications.export', request()->query()) }}" class="btn">⬇️ Export CSV</a>
</div>
<form method="get" class="card tight mb row">
  <select name="product" style="max-width:240px"><option value="">All checks</option>@foreach ($products as $k => $p)<option value="{{ $k }}" @selected(($f['product'] ?? '') === $k)>{{ $p['emoji'] }} {{ $p['name'] }}</option>@endforeach</select>
  <select name="state" style="max-width:200px"><option value="">Any result</option>@foreach (['valid' => 'Verified', 'invalid' => 'Not verified', 'pending' => 'Waiting', 'failed' => 'Failed'] as $k => $l)<option value="{{ $k }}" @selected(($f['state'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
  <input type="text" name="q" value="{{ $f['q'] ?? '' }}" placeholder="ID or your reference" style="max-width:240px">
  <button class="btn btn-lime" type="submit">Filter</button>@if(array_filter($f))<a href="{{ route('verifications') }}" class="btn btn-ghost">Clear</a>@endif
</form>
<div class="card">@include('app._vtable', ['items' => $items])@include('partials.pager', ['p' => $items])</div>
@endsection
