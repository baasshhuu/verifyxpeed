@extends('layouts.app')
@section('title','Top-ups')
@section('content')
@php($M = \App\Support\Money::class)
<div class="page-h"><div><h1 class="title">Top-ups 💸</h1><p class="sub">Cashfree payments credit automatically. Bank transfers wait here until you match the UTR in your bank statement.</p></div></div>
<div class="card tight mb"><div class="tabs">@foreach (['' => 'All', 'pending_approval' => '⏳ To match', 'paid' => '✅ Paid online', 'approved' => '🏦 Approved', 'created' => '🕓 Started', 'failed' => '💥 Failed', 'rejected' => '🚫 Rejected'] as $k => $l)<a href="{{ route('admin.topups', array_filter(['status' => $k])) }}" class="{{ ($f['status'] ?? '') === $k ? 'on' : '' }}">{{ $l }}</a>@endforeach</div></div>
<div class="card">
@if ($items->isEmpty())<div class="empty"><span class="e">🪙</span>No top-ups here.</div>@else
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Customer</th><th>Order</th><th>Method</th><th class="r">Amount</th><th>Status</th><th></th></tr></thead><tbody>
@foreach ($items as $t)<tr><td class="xs muted">{{ $t->created_at->format('d M h:i a') }}</td><td><a class="b" href="{{ route('admin.customer', $t->user) }}">{{ \Illuminate\Support\Str::limit($t->user->displayName(), 24) }}</a></td><td class="mono xs">{{ $t->order_id }}</td><td class="sm">{{ $t->method === 'pg' ? 'Cashfree PG' : 'Bank · UTR ' . $t->utr }}</td><td class="r b">{{ $M::inr($t->amount_paise) }}</td><td><span class="badge {{ in_array($t->status, ['paid','approved']) ? 'ok' : (in_array($t->status, ['failed','rejected']) ? 'err' : 'warn') }}">{{ str_replace('_', ' ', $t->status) }}</span></td>
<td class="row" style="flex-wrap:nowrap">@if ($t->status === 'pending_approval')<form method="post" action="{{ route('admin.topups.review', $t) }}" onsubmit="return confirm('Credit {{ $M::inr($t->amount_paise) }} to this wallet?')">@csrf<button class="btn btn-sm btn-lime">Approve</button></form><form method="post" action="{{ route('admin.topups.review', $t) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-danger">Reject</button></form>@elseif ($t->method === 'pg' && !$t->credited_at)<form method="post" action="{{ route('admin.topups.recheck', $t) }}">@csrf<button class="btn btn-sm">Re-check</button></form>@endif</td></tr>@endforeach
</tbody></table></div>@include('partials.pager', ['p' => $items])@endif
</div>
@endsection
