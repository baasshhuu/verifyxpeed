<?php

namespace App\Models;

use App\Support\Catalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ProductSetting extends Model
{
    protected $fillable = ['product', 'enabled', 'price_paise', 'cost_paise'];
    protected function casts(): array { return ['enabled' => 'boolean']; }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('vx_product_settings'));
    }

    /** product => ['enabled', 'price_paise', 'cost_paise'] merged with catalog defaults */
    public static function map(): array
    {
        $rows = Cache::remember('vx_product_settings', 300, fn () => self::all()->keyBy('product')->toArray());
        $out = [];
        foreach (Catalog::all() as $k => $p) {
            $r = $rows[$k] ?? null;
            $out[$k] = [
                'enabled' => $r ? (bool) $r['enabled'] : true,
                'price_paise' => $r ? (int) $r['price_paise'] : (int) round($p['price'] * 100),
                'cost_paise' => $r ? (int) $r['cost_paise'] : (int) round($p['cost'] * 100),
            ];
        }
        return $out;
    }

    public static function for(string $product): array
    {
        return self::map()[$product];
    }
}
