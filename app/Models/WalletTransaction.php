<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = ['wallet_id', 'txn_id', 'type', 'amount_paise', 'balance_after_paise', 'description', 'source_type', 'source_id', 'created_by'];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function source(): MorphTo { return $this->morphTo(); }
}
