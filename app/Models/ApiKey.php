<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['user_id', 'name', 'mode', 'prefix', 'key_hash', 'ip_whitelist', 'last_used_at', 'revoked_at'];
    protected function casts(): array { return ['ip_whitelist' => 'array', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** @return array{0: ApiKey, 1: string} model + plain key (shown once) */
    public static function generate(User $user, string $name, string $mode): array
    {
        $plain = 'vx_' . $mode . '_' . Str::random(40);
        $key = self::create(['user_id' => $user->id, 'name' => $name, 'mode' => $mode, 'prefix' => substr($plain, 0, 14), 'key_hash' => hash('sha256', $plain)]);
        return [$key, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return self::where('key_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
    }
}
