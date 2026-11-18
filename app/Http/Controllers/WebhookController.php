<?php

namespace App\Http\Controllers;

use App\Models\Topup;
use App\Models\Verification;
use App\Services\Cashfree\PaymentGateway;
use App\Services\TopupService;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /** Cashfree Secure ID webhooks (DigiLocker, e-sign, reverse penny drop, KYC link, VKYC). */
    public function secureId(Request $r, VerificationService $svc)
    {
        $raw = $r->getContent();
        $ts = $r->header('x-webhook-timestamp'); $sig = $r->header('x-webhook-signature');
        $ok = false;
        foreach (['sandbox', 'production'] as $env) {
            $secret = config("verifyxpeed.cashfree.secureid.$env.client_secret");
            if ($secret && $ts && $sig && hash_equals(base64_encode(hash_hmac('sha256', $ts . $raw, $secret, true)), $sig)) { $ok = true; break; }
        }
        if (! $ok) {
            Log::warning('Secure ID webhook with bad signature');
            return response()->json(['ok' => false], 401);
        }
        $vid = $this->findVid(json_decode($raw, true) ?: []);
        if ($vid && ($v = Verification::where('verification_id', $vid)->first())) {
            $svc->refresh($v);
        }
        return response()->json(['ok' => true]);
    }

    private function findVid(array $a): ?string
    {
        foreach ($a as $k => $val) {
            if ($k === 'verification_id' && is_string($val) && str_starts_with($val, 'VX')) return $val;
            if (is_array($val) && ($f = $this->findVid($val))) return $f;
        }
        return null;
    }

    /** Cashfree PG payment webhook */
    public function pg(Request $r, PaymentGateway $pg, TopupService $svc)
    {
        if (! $pg->validSignature($r->getContent(), $r->header('x-webhook-timestamp'), $r->header('x-webhook-signature'))) {
            return response()->json(['ok' => false], 401);
        }
        $orderId = data_get($r->json()->all(), 'data.order.order_id');
        if ($orderId && ($t = Topup::where('order_id', $orderId)->first())) $svc->confirm($t);
        return response()->json(['ok' => true]);
    }

    /** Where DigiLocker / reverse penny / e-sign send the end user afterwards. */
    public function done(string $vid)
    {
        $v = Verification::where('verification_id', $vid)->first();
        return view('public.done', ['v' => $v]);
    }
}
