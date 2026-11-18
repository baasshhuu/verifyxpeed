@extends('layouts.app')
@section('title','Paying…')
@section('content')
<div class="card center" style="max-width:520px;margin:40px auto;padding:40px">
  <div style="font-size:56px">💳</div>
  <h1 class="title" style="font-size:28px;margin-top:8px">Opening Cashfree…</h1>
  <p class="muted">Paying {{ \App\Support\Money::inr($t->amount_paise) }} · order <span class="mono">{{ $t->order_id }}</span></p>
  <button class="btn btn-lime btn-lg" id="payBtn" style="margin-top:12px">Pay now →</button>
  <p class="hint" style="margin-top:14px"><a class="link" href="{{ route('wallet') }}">Cancel</a></p>
</div>
@push('scripts')
<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script>
(function(){
  function go(){ try { Cashfree({ mode: @json($mode) }).checkout({ paymentSessionId: @json($t->payment_session_id), redirectTarget: "_self" }); } catch(e){ alert('Could not open Cashfree checkout. Please try again.'); } }
  document.getElementById('payBtn').addEventListener('click', go);
  window.addEventListener('load', function(){ setTimeout(go, 300); });
})();
</script>
@endpush
@endsection
