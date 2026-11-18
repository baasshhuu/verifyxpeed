<?php

namespace App\Models;

use App\Support\Catalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkJob extends Model
{
    protected $fillable = ['user_id', 'product', 'file_name', 'path', 'total', 'processed', 'valid', 'invalid', 'failed', 'status', 'note'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function productName(): string { return Catalog::get($this->product)['name'] ?? $this->product; }
    public function percent(): int { return $this->total ? (int) floor($this->processed / $this->total * 100) : 0; }
}
