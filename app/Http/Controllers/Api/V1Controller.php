<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductSetting;
use App\Models\Verification;
use App\Services\InsufficientBalance;
use App\Services\ProductUnavailable;
use App\Services\VerificationService;
use App\Support\Catalog;
use App\Support\InputRules;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class V1Controller extends Controller
{
    private function err(string $code, string $msg, int $status, array $extra = [])
    {
        return response()->json(['error' => ['code' => $code, 'message' => $msg] + $extra], $status);
    }

    private function mine(Request $r, string $vid): ?Verification
    {
        return Verification::where('verification_id', $vid)->where('user_id', $r->user()->id)->first();
    }

    public function products()
    {
        $prices = ProductSetting::map();
        return response()->json(['data' => collect(Catalog::all())->filter(fn ($p) => $prices[$p['key']]['enabled'])->map(fn ($p) => [
            'product' => $p['key'], 'name' => $p['name'], 'category' => $p['cat'], 'flow' => $p['flow'],
            'price' => round($prices[$p['key']]['price_paise'] / 100, 2), 'price_with_gst' => round(Money::withGst($prices[$p['key']]['price_paise']) / 100, 2),
            'content_type' => $p['body'] === 'multipart' || collect($p['fields'])->contains('type', 'file') ? 'multipart/form-data' : 'application/json',
            'fields' => collect($p['fields'])->map(fn ($f) => ['name' => $f['name'], 'type' => $f['type'], 'required' => in_array('required', $f['rules'], true), 'options' => isset($f['options']) ? array_keys($f['options']) : null])->values(),
        ])->values()]);
    }

    public function wallet(Request $r)
    {
        return response()->json(['data' => ['balance' => round($r->user()->walletModel()->balance_paise / 100, 2), 'currency' => 'INR']]);
    }

    public function verify(Request $r, string $product, VerificationService $svc)
    {
        $p = Catalog::get($product);
        if (! $p) return $this->err('product_not_found', "Unknown product '$product'. GET /api/v1/products for the list.", 404);
        $key = $r->attributes->get('api_key');
        $live = $key->mode === 'live';
        try {
            $data = InputRules::validate($p, $r->all() + $r->allFiles());
        } catch (ValidationException $e) {
            return $this->err('validation_error', 'Some fields are invalid', 422, ['fields' => $e->errors()]);
        }
        try {
            $v = $svc->start($r->user(), $product, $data, 'api', $live ? 'production' : 'sandbox', null, mb_substr((string) $r->input('reference'), 0, 100) ?: null, $live);
        } catch (InsufficientBalance) {
            return $this->err('insufficient_balance', 'Wallet balance is too low. Top up at ' . route('wallet'), 402);
        } catch (ProductUnavailable $e) {
            return $this->err('product_disabled', $e->getMessage(), 403);
        }
        return response()->json(['data' => $v->apiPayload()], $v->state === 'failed' ? 502 : 200);
    }

    public function show(Request $r, string $vid)
    {
        $v = $this->mine($r, $vid) ?? null;
        return $v ? response()->json(['data' => $v->apiPayload()]) : $this->err('not_found', 'Verification not found', 404);
    }

    public function refresh(Request $r, string $vid, VerificationService $svc)
    {
        $v = $this->mine($r, $vid);
        if (! $v) return $this->err('not_found', 'Verification not found', 404);
        return response()->json(['data' => $svc->refresh($v)->apiPayload()]);
    }

    public function otp(Request $r, string $vid, VerificationService $svc)
    {
        $v = $this->mine($r, $vid);
        if (! $v) return $this->err('not_found', 'Verification not found', 404);
        $otp = (string) $r->input('otp');
        if (! preg_match('/^\d{4,8}$/', $otp)) return $this->err('validation_error', 'otp must be 4-8 digits', 422);
        return response()->json(['data' => $svc->verifyOtp($v, $otp)->apiPayload()]);
    }

    public function document(Request $r, string $vid, string $type, VerificationService $svc)
    {
        $v = $this->mine($r, $vid);
        if (! $v) return $this->err('not_found', 'Verification not found', 404);
        $res = $svc->fetchDocument($v, $type);
        return $res['ok'] ? response()->json(['data' => $res['data']]) : $this->err('document_unavailable', (string) $res['message'], 422);
    }
}
