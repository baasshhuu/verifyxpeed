<?php

namespace App\Support;

class Money
{
    public static function inr(int|float|null $paise, bool $decimals = true): string
    {
        $p = (int) round($paise ?? 0);
        $neg = $p < 0; $p = abs($p);
        $whole = (string) intdiv($p, 100);
        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3));
            $whole = $rest . ',' . $last3;
        }
        $dec = $decimals ? '.' . str_pad((string) ($p % 100), 2, '0', STR_PAD_LEFT) : '';
        return ($neg ? '−' : '') . '₹' . $whole . $dec;
    }

    /** price before GST -> amount charged incl. GST, in paise */
    public static function withGst(int $pricePaise): int
    {
        return (int) round($pricePaise * (1 + config('verifyxpeed.gst_rate')));
    }
}
