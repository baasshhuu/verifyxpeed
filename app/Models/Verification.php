<?php

namespace App\Models;

use App\Support\Catalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verification extends Model
{
    protected $fillable = ['user_id', 'verification_id', 'product', 'env', 'source', 'bulk_job_id', 'state', 'outcome', 'provider_status', 'reference_id', 'input_masked', 'response', 'error_code', 'error_message', 'charged_paise', 'cost_paise', 'refunded', 'client_reference'];

    protected function casts(): array
    {
        return ['input_masked' => 'array', 'response' => 'encrypted:array', 'refunded' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getRouteKeyName(): string { return 'verification_id'; }

    public function meta(): ?array { return Catalog::get($this->product); }

    public function productName(): string { return $this->meta()['name'] ?? $this->product; }

    public function badge(): array
    {
        return match (true) {
            $this->state === 'failed' => ['err', $this->refunded ? 'failed · refunded' : 'failed'],
            $this->state === 'pending' => ['warn', 'waiting on person'],
            $this->state === 'processing' => ['warn', 'processing'],
            $this->outcome === 'valid' => ['ok', 'verified ✨'],
            $this->outcome === 'invalid' => ['bad', 'not verified'],
            default => ['muted', $this->state],
        };
    }

    public function link(): ?string
    {
        $k = $this->meta()['link_key'] ?? null;
        return $k ? data_get($this->response, 'create.' . $k) : null;
    }

    public function apiPayload(): array
    {
        return [
            'verification_id' => $this->verification_id,
            'product' => $this->product,
            'env' => $this->env,
            'state' => $this->state,
            'outcome' => $this->outcome,
            'provider_status' => $this->provider_status,
            'charged' => round($this->charged_paise / 100, 2),
            'refunded' => $this->refunded,
            'reference' => $this->client_reference,
            'link' => $this->link(),
            'error' => $this->error_code ? ['code' => $this->error_code, 'message' => $this->error_message] : null,
            'data' => $this->response['result'] ?? null,
            'documents' => $this->response['documents'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
