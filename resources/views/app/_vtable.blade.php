@if ($items->isEmpty())
  <div class="empty"><span class="e">🫥</span>No checks yet. <a class="link" href="{{ route('checks') }}">Run your first one →</a></div>
@else
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Check</th><th>Input</th><th>Via</th><th>Result</th><th class="r">Charged</th><th>When</th><th></th></tr></thead>
  <tbody>
  @foreach ($items as $v)
    <tr>
      <td><div class="prod-cell"><span class="e">{{ $v->meta()['emoji'] ?? '🔍' }}</span><b>{{ $v->productName() }}</b></div></td>
      <td class="muted sm">{{ \Illuminate\Support\Str::limit(collect($v->input_masked)->first() ?? '—', 28) }}</td>
      <td><span class="badge muted">{{ $v->source }}</span></td>
      <td>@include('partials.vbadge')</td>
      <td class="r">@if($v->refunded)<s class="dim">{{ \App\Support\Money::inr($v->charged_paise) }}</s>@else{{ \App\Support\Money::inr($v->charged_paise) }}@endif</td>
      <td class="muted sm">{{ $v->created_at->diffForHumans(null, true) }} ago</td>
      <td><a href="{{ route('verifications.show', $v) }}" class="btn btn-sm">View</a></td>
    </tr>
  @endforeach
  </tbody>
</table></div>
@endif
