<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** Admin-editable settings (Cashfree keys etc.), encrypted at rest. They override .env. */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** setting key => config path it overrides */
    public const MAP = [
        'cf_sandbox_id' => 'verifyxpeed.cashfree.secureid.sandbox.client_id',
        'cf_sandbox_secret' => 'verifyxpeed.cashfree.secureid.sandbox.client_secret',
        'cf_sandbox_pubkey' => 'verifyxpeed.cashfree.secureid.sandbox.public_key_path',
        'cf_prod_id' => 'verifyxpeed.cashfree.secureid.production.client_id',
        'cf_prod_secret' => 'verifyxpeed.cashfree.secureid.production.client_secret',
        'cf_prod_pubkey' => 'verifyxpeed.cashfree.secureid.production.public_key_path',
        'pg_env' => 'verifyxpeed.cashfree.pg.env',
        'pg_app_id' => 'verifyxpeed.cashfree.pg.app_id',
        'pg_secret' => 'verifyxpeed.cashfree.pg.secret',
        'web_env' => 'verifyxpeed.web_env',
        'kyc_template' => 'verifyxpeed.cashfree.kyc_link_template',
        'vkyc_user_template' => 'verifyxpeed.cashfree.vkyc_user_template',
        'vkyc_agent_template' => 'verifyxpeed.cashfree.vkyc_agent_template',
        'bank_details' => 'verifyxpeed.topup.bank_details',
    ];

    public static function allDecrypted(): array
    {
        return Cache::rememberForever('vx_settings', function () {
            $out = [];
            foreach (self::all() as $s) {
                try { $out[$s->key] = $s->value === null ? null : Crypt::decryptString($s->value); } catch (\Throwable) {}
            }
            return $out;
        });
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value === null || $value === '' ? null : Crypt::encryptString($value)]);
        Cache::forget('vx_settings');
    }

    /** Copy saved settings over config at boot. */
    public static function applyToConfig(): void
    {
        foreach (self::allDecrypted() as $k => $v) {
            if ($v !== null && $v !== '' && isset(self::MAP[$k])) config([self::MAP[$k] => $v]);
        }
    }
}
