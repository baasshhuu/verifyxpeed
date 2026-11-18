<?php

namespace App\Support;

/** Turns Cashfree response values into safe, readable HTML. */
class Present
{
    public static function label(string $key): string
    {
        $k = last(explode('.', $key));
        $k = str_replace(['_', '-'], ' ', $k);
        $k = preg_replace('/\bgst in\b/i', 'GSTIN', $k);
        $map = ['dob' => 'Date of birth', 'ifsc' => 'IFSC', 'utr' => 'UTR', 'micr' => 'MICR', 'uid' => 'Aadhaar', 'vpa' => 'UPI ID', 'pan' => 'PAN', 'cin' => 'CIN', 'din' => 'DIN', 'uan' => 'UAN', 'rc status' => 'RC status', 'ip address' => 'IP address'];
        $lk = strtolower($k);
        return $map[$lk] ?? ucfirst($lk);
    }

    public static function value(string $key, mixed $v, int $depth = 0): string
    {
        if ($v === null || $v === '') return '<span class="dim">—</span>';
        if (is_bool($v)) return $v ? '✅ yes' : '❌ no';
        if (is_array($v)) {
            if ($v === []) return '<span class="dim">none</span>';
            if (array_is_list($v) && collect($v)->every(fn ($x) => is_scalar($x) || $x === null)) {
                return collect($v)->map(fn ($x) => '<span class="val-chip">' . e((string) $x) . '</span>')->implode('');
            }
            if ($depth > 3) return '<code>' . e(mb_substr(json_encode($v), 0, 300)) . '</code>';
            return self::table($v, $depth + 1);
        }
        $s = (string) $v;
        $lk = strtolower($key);
        if ((str_contains($lk, 'photo') || str_contains($lk, 'image')) && strlen($s) > 200 && ! str_starts_with($s, 'http')) {
            $src = str_starts_with($s, 'data:') ? $s : 'data:image/jpeg;base64,' . $s;
            return '<img class="photo" alt="photo" src="' . e($src) . '">';
        }
        if (str_contains($lk, 'qr_code') && strlen($s) > 200) {
            $src = str_starts_with($s, 'data:') ? $s : 'data:image/png;base64,' . $s;
            return '<img class="qr" alt="QR" src="' . e($src) . '">';
        }
        if (preg_match('#^https?://#', $s)) {
            return '<a class="link" target="_blank" rel="noopener" href="' . e($s) . '">Open ↗</a> <span class="dim xs">' . e(mb_strimwidth($s, 0, 48, '…')) . '</span>';
        }
        $up = strtoupper($s);
        $good = ['VALID', 'SUCCESS', 'ACTIVE', 'YES', 'Y', 'DIRECT_MATCH', 'GOOD_PARTIAL_MATCH', 'ACCOUNT_IS_VALID', 'AUTHENTICATED', 'ACCOUNT_EXISTS', 'LIVE', 'E'];
        $bad = ['INVALID', 'NO', 'N', 'NO_MATCH', 'FAILED', 'FAILURE', 'EXPIRED', 'CANCELLED', 'BLACKLISTED', 'INACTIVE', 'POOR_PARTIAL_MATCH', 'CONSENT_DENIED'];
        if (in_array($up, $good, true) && strlen($s) > 1) return '<span class="badge ok">' . e($s) . '</span>';
        if (in_array($up, $bad, true) && strlen($s) > 1) return '<span class="badge bad">' . e($s) . '</span>';
        return e(mb_strimwidth($s, 0, 600, '…'));
    }

    public static function table(array $data, int $depth = 0): string
    {
        $h = '<table class="kv">';
        foreach ($data as $k => $v) {
            $label = is_int($k) ? '#' . ($k + 1) : self::label((string) $k);
            $h .= '<tr><td>' . e($label) . '</td><td>' . self::value((string) $k, $v, $depth) . '</td></tr>';
        }
        return $h . '</table>';
    }
}
