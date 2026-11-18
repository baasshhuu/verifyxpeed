<?php

namespace App\Jobs;

use App\Models\BulkJob;
use App\Services\InsufficientBalance;
use App\Services\VerificationService;
use App\Support\Catalog;
use App\Support\InputRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProcessBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public int $bulkJobId) {}

    public function handle(VerificationService $svc): void
    {
        $job = BulkJob::find($this->bulkJobId);
        if (! $job || in_array($job->status, ['done', 'stopped'], true)) return;
        $p = Catalog::get($job->product);
        $job->update(['status' => 'running']);

        $fh = fopen(Storage::path($job->path), 'r');
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), fgetcsv($fh) ?: []);
        $refCol = array_search('reference', array_map('strtolower', $header), true);
        $row = 0;
        while (($line = fgetcsv($fh)) !== false) {
            if (count(array_filter($line, fn ($x) => trim((string) $x) !== '')) === 0) continue;
            $row++;
            if ($row <= $job->processed) continue; // resume support
            $data = [];
            foreach ($header as $i => $h) $data[$h] = isset($line[$i]) ? trim($line[$i]) : null;
            try {
                $input = InputRules::validate($p, $data);
                $v = $svc->start($job->user, $job->product, $input, 'bulk', null, $job->id, $refCol !== false ? ($line[$refCol] ?? null) : null);
                $col = $v->state === 'failed' ? 'failed' : ($v->outcome === 'valid' ? 'valid' : 'invalid');
                $job->increment($col);
            } catch (ValidationException $e) {
                $job->increment('failed');
            } catch (InsufficientBalance) {
                $job->update(['status' => 'stopped', 'note' => "Stopped at row $row: wallet ran out. Top up and press Resume."]);
                fclose($fh);
                return;
            }
            $job->increment('processed');
        }
        fclose($fh);
        $job->update(['status' => 'done', 'note' => null]);
    }
}
