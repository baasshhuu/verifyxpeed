@extends('layouts.app')
@section('title','Pricing')
@section('content')
<div class="page-h"><div><h1 class="title">Pricing & margins 🏷️</h1><p class="sub">Set your selling price (before GST) and what Cashfree charges you per call. Default costs are placeholders: put in your real Cashfree rates.</p></div></div>
<form method="post" action="{{ route('admin.pricing.save') }}">@csrf
<div class="card">
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>On</th><th>Check</th><th>Your cost (₹)</th><th>Selling price (₹)</th><th class="r">Customer pays</th><th class="r">Margin</th></tr></thead><tbody>
  @foreach ($products as $k => $p)
    @php($c = $prices[$k]['cost_paise'] / 100)
    @php($pr = $prices[$k]['price_paise'] / 100)
    <tr>
      <td><label class="check" style="margin:0"><input type="checkbox" name="enabled[{{ $k }}]" value="1" @checked($prices[$k]['enabled'])></label></td>
      <td><div class="prod-cell"><span class="e">{{ $p['emoji'] }}</span><div><b>{{ $p['name'] }}</b><div class="xs muted">{{ \App\Support\Catalog::CATEGORIES[$p['cat']]['label'] }} · {{ $p['flow'] }}</div></div></div></td>
      <td><input type="number" step="0.01" min="0" name="cost[{{ $k }}]" value="{{ $c }}" style="width:110px"></td>
      <td><input type="number" step="0.01" min="0" name="price[{{ $k }}]" value="{{ $pr }}" style="width:110px"></td>
      <td class="r">{{ \App\Support\Money::inr(\App\Support\Money::withGst($prices[$k]['price_paise'])) }}</td>
      <td class="r"><b class="{{ $pr - $c < 0 ? 'neg' : 'pos' }}">₹{{ number_format($pr - $c, 2) }}</b> <span class="xs muted">{{ $pr > 0 ? round(($pr - $c) / $pr * 100) : 0 }}%</span></td>
    </tr>
  @endforeach
  </tbody></table></div>
</div>
<div class="row mt"><button class="btn btn-lime btn-lg" type="submit">💾 Save pricing</button><span class="hint">Unticked checks disappear for customers and the API.</span></div>
</form>
@endsection
