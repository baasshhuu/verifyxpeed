<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderLog extends Model
{
    protected $fillable = ['provider', 'env', 'method', 'endpoint', 'http_status', 'latency_ms', 'verification_id', 'error_code', 'message'];
}
