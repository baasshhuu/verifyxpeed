<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkJob;
use App\Models\BulkJob;
use App\Models\ProductSetting;
use App\Support\Catalog;
use App\Support\Money;
use Illuminate\Http\Request;

class BulkController extends Controller
{
    public function index(Request $r)
    {
        $products = collect(Catalog::bulkable())->filter(fn ($p) => ProductSetting::for($p['key'])['enabled']);
        return view('app.bulk', ['products' => $products, 'jobs' => $r->user()->bulkJobs()->latest('id')->limit(20)->get(), 'prices' => ProductSetting::map(), 'block' => $r->user()->verifyBlockReason()]);
    }

    public function store(Request $r)
    {
        if ($why = $r->user()->verifyBlockReason()) return back()->with('err', $why);
        $keys = array_keys(Catalog::bulkable());
        $d = $r->validate(['product' => 'required|in:' . implode(',', $keys), 'file' => 'required|file|mimes:csv,txt|max:5120', 'consent' => 'accepted'], ['consent.accepted' => 'Please confirm consent was collected for everyone in the file.']);
        $p = Catalog::get($d['product']);
        $path = $r->file('file')->store('bulk');
        $rows = 0; $fh = fopen($r->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), fgetcsv($fh) ?: []);
        while (($l = fgetcsv($fh)) !== false) if (count(array_filter($l, fn ($x) => trim((string) $x) !== ''))) $rows++;
        fclose($fh);
        $required = collect($p['fields'])->filter(fn ($f) => in_array('required', $f['rules'], true))->pluck('name');
        $missing = $required->diff($header);
        if ($missing->isNotEmpty()) return back()->with('err', 'CSV is missing column(s): ' . $missing->implode(', '));
        if ($rows === 0) return back()->with('err', 'The file has no rows.');
        if ($rows > config('verifyxpeed.bulk_max_rows')) return back()->with('err', 'Max ' . config('verifyxpeed.bulk_max_rows') . ' rows per file.');
        $need = Money::withGst(ProductSetting::for($p['key'])['price_paise']) * $rows;
        $bal = $r->user()->walletModel()->balance_paise;
        $job = BulkJob::create(['user_id' => $r->user()->id, 'product' => $p['key'], 'file_name' => mb_substr($r->file('file')->getClientOriginalName(), 0, 200), 'path' => $path, 'total' => $rows]);
        ProcessBulkJob::dispatch($job->id);
        $msg = "Bulk job started for $rows rows 🚀";
        if ($bal < $need) $msg .= ' Heads up: your wallet covers about ' . intdiv($bal, max(1, intdiv($need, $rows))) . ' rows. It will pause when money runs out.';
        return redirect()->route('bulk.show', $job)->with('ok', $msg);
    }

    public function show(Request $r, BulkJob $job)
    {
        abort_unless($job->user_id === $r->user()->id, 404);
        $items = \App\Models\Verification::where('bulk_job_id', $job->id)->latest('id')->paginate(25);
        return view('app.bulk-show', compact('job', 'items'));
    }

    public function resume(Request $r, BulkJob $job)
    {
        abort_unless($job->user_id === $r->user()->id, 404);
        if ($job->status === 'stopped') { $job->update(['status' => 'queued', 'note' => null]); ProcessBulkJob::dispatch($job->id); }
        return back()->with('ok', 'Resumed ▶️');
    }

    public function download(Request $r, BulkJob $job)
    {
        abort_unless($job->user_id === $r->user()->id, 404);
        $rows = \App\Models\Verification::where('bulk_job_id', $job->id)->orderBy('id')->get();
        $out = fopen('php://temp', 'r+');
        $keys = Catalog::get($job->product)['highlights'] ?? [];
        fputcsv($out, array_merge(['row', 'reference', 'verification_id', 'state', 'outcome', 'provider_status', 'error'], $keys));
        foreach ($rows as $i => $v) {
            $res = $v->response['result'] ?? [];
            fputcsv($out, array_merge([$i + 1, $v->client_reference, $v->verification_id, $v->state, $v->outcome, $v->provider_status, $v->error_message], array_map(fn ($k) => is_scalar($x = data_get($res, $k)) || $x === null ? $x : json_encode($x), $keys)));
        }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="bulk-' . $job->id . '-results.csv"']);
    }

    public function sample(string $product)
    {
        $p = Catalog::bulkable()[$product] ?? abort(404);
        $cols = array_merge(array_column($p['fields'], 'name'), ['reference']);
        $test = $p['test'];
        $line = array_map(fn ($c) => $test[$c] ?? ($c === 'reference' ? 'cust-001' : ''), $cols);
        return response(implode(',', $cols) . "\n" . implode(',', $line) . "\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"sample-$product.csv\""]);
    }
}
