<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topup extends Model
{
    protected $fillable = ['user_id', 'order_id', 'method', 'amount_paise', 'status', 'payment_session_id', 'utr', 'note', 'gateway_response', 'reviewed_by', 'credited_at'];
    protected function casts(): array { return ['gateway_response' => 'array', 'credited_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
