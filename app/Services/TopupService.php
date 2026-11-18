<?php

namespace App\Services;

use App\Models\Topup;
use App\Services\Cashfree\PaymentGateway;
use Illuminate\Support\Facades\DB;

class TopupService
{
    public function __construct(private PaymentGateway $pg, private WalletService $wallet) {}

    /** Ask Cashfree whether the order is paid; credit the wallet exactly once. */
    public function confirm(Topup $t): Topup
    {
        if ($t->method !== 'pg' || $t->credited_at) return $t;
        $r = $this->pg->getOrder($t->order_id);
        if (! $r->ok()) return $t;
        $status = strtoupper((string) $r->get('order_status'));
        $paidAmount = (int) round(((float) $r->get('order_amount', 0)) * 100);

        DB::transaction(function () use ($t, $status, $r, $paidAmount) {
            $locked = Topup::whereKey($t->id)->lockForUpdate()->first();
            if ($locked->credited_at) return;
            $locked->gateway_response = $r->data;
            if ($status === 'PAID' && $paidAmount === (int) $locked->amount_paise) {
                $locked->status = 'paid';
                $locked->credited_at = now();
                $locked->save();
                $this->wallet->credit($locked->user, $locked->amount_paise, 'Wallet top-up · Cashfree · ' . $locked->order_id, $locked);
            } elseif (in_array($status, ['EXPIRED', 'TERMINATED', 'TERMINATION_REQUESTED'], true)) {
                $locked->status = 'failed';
                $locked->save();
            } else {
                $locked->save();
            }
        });
        return $t->fresh();
    }

    public function approveBank(Topup $t, int $adminId): void
    {
        DB::transaction(function () use ($t, $adminId) {
            $locked = Topup::whereKey($t->id)->lockForUpdate()->first();
            if ($locked->credited_at || $locked->status !== 'pending_approval') return;
            $locked->fill(['status' => 'approved', 'reviewed_by' => $adminId, 'credited_at' => now()])->save();
            $this->wallet->credit($locked->user, $locked->amount_paise, 'Bank transfer top-up · UTR ' . $locked->utr, $locked, 'credit', $adminId);
        });
    }
}
