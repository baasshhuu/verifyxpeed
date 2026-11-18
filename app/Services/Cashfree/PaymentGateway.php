<?php

namespace App\Services\Cashfree;

use App\Models\Topup;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Cashfree Payment Gateway (PG) for wallet top-ups. Uses its own app id / secret. */
class PaymentGateway
{
    public function cfg(): array { return config('verifyxpeed.cashfree.pg'); }

    public function env(): string { return $this->cfg()['env'] === 'production' ? 'production' : 'sandbox'; }

    public function configured(): bool { return filled($this->cfg()['app_id']) && filled($this->cfg()['secret']); }

    private function base(): string
    {
        if ($b = $this->cfg()['base_url'] ?? null) return rtrim($b, '/');
        return $this->env() === 'production' ? 'https://api.cashfree.com/pg' : 'https://sandbox.cashfree.com/pg';
    }

    private function http()
    {
        return Http::withHeaders([
            'x-client-id' => $this->cfg()['app_id'],
            'x-client-secret' => $this->cfg()['secret'],
            'x-api-version' => $this->cfg()['api_version'],
            'Accept' => 'application/json',
        ])->timeout(30);
    }

    public function createOrder(Topup $t, User $u): CashfreeResponse
    {
        if (! $this->configured()) return new CashfreeResponse(0, ['code' => 'not_configured', 'message' => 'Online top-up is not set up yet (Cashfree PG keys missing). Use bank transfer for now.']);
        $return = route('wallet.topup.return') . '?order_id={order_id}';
        $meta = ['return_url' => $return];
        $notify = route('webhooks.pg');
        if (str_starts_with($notify, 'https://')) $meta['notify_url'] = $notify;
        $body = [
            'order_id' => $t->order_id,
            'order_amount' => round($t->amount_paise / 100, 2),
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => 'VXU' . $u->id,
                'customer_phone' => substr(preg_replace('/\D/', '', (string) $u->phone), -10) ?: '9999999999',
                'customer_email' => $u->email,
                'customer_name' => mb_substr($u->displayName(), 0, 100),
            ],
            'order_meta' => $meta,
            'order_note' => 'VerifyXpeed wallet top-up',
        ];
        try {
            $r = $this->http()->post($this->base() . '/orders', $body);
        } catch (ConnectionException $e) {
            return CashfreeResponse::connectionError($e->getMessage());
        }
        return new CashfreeResponse($r->status(), (array) $r->json());
    }

    public function getOrder(string $orderId): CashfreeResponse
    {
        if (! $this->configured()) return new CashfreeResponse(0, ['code' => 'not_configured', 'message' => 'PG not configured']);
        try {
            $r = $this->http()->get($this->base() . '/orders/' . urlencode($orderId));
        } catch (ConnectionException $e) {
            return CashfreeResponse::connectionError($e->getMessage());
        }
        return new CashfreeResponse($r->status(), (array) $r->json());
    }

    public function validSignature(string $raw, ?string $ts, ?string $sig): bool
    {
        if (! $ts || ! $sig || ! $this->configured()) return false;
        $calc = base64_encode(hash_hmac('sha256', $ts . $raw, $this->cfg()['secret'], true));
        return hash_equals($calc, $sig);
    }
}
