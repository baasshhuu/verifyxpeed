@extends('layouts.app')
@section('title','Customers')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">Customers 👥</h1><p class="sub">Approve businesses before they can run checks. Reject anyone who can't show a lawful purpose.</p></div></div>
<form method="get" class="card tight mb row">
  <div class="tabs">@foreach (['' => 'All', 'pending' => '⏳ Pending', 'approved' => '✅ Approved', 'rejected' => '🚫 Rejected'] as $k => $l)<a href="{{ route('admin.customers', array_filter(['kyb' => $k, 'q' => $f['q'] ?? null])) }}" class="{{ ($f['kyb'] ?? '') === $k ? 'on' : '' }}">{{ $l }}</a>@endforeach</div>
  <input type="text" name="q" value="{{ $f['q'] ?? '' }}" placeholder="business, email or phone" style="max-width:260px">@if(!empty($f['kyb']))<input type="hidden" name="kyb" value="{{ $f['kyb'] }}">@endif<button class="btn btn-lime">Search</button>
</form>
<div class="card">
@if ($users->isEmpty())<div class="empty"><span class="e">🫥</span>No customers here.</div>@else
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Business</th><th>Type</th><th>Contact</th><th class="r">Wallet</th><th class="r">Checks</th><th>KYB</th><th>Account</th><th></th></tr></thead><tbody>
@foreach ($users as $u)<tr><td><b>{{ $u->displayName() }}</b><div class="xs muted">joined {{ $u->created_at->format('d M Y') }}</div></td><td class="sm">{{ $u->business_type }}</td><td class="sm">{{ $u->email }}<div class="xs muted">{{ $u->phone }}</div></td><td class="r">{{ $M::inr($u->wallet?->balance_paise ?? 0) }}</td><td class="r">{{ $u->verifications_count }}</td><td><span class="badge {{ ['approved'=>'ok','pending'=>'warn','rejected'=>'err'][$u->kyb_status] }}">{{ $u->kyb_status }}</span></td><td><span class="badge {{ $u->status === 'active' ? 'muted' : 'err' }}">{{ $u->status }}</span></td><td><a class="btn btn-sm" href="{{ route('admin.customer', $u) }}">Open</a></td></tr>@endforeach
</tbody></table></div>@include('partials.pager', ['p' => $users])@endif
</div>
@endsection
