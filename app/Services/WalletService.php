<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All wallet money moves through here. Amounts are integer paise.
 * Every movement locks the wallet row and writes a ledger entry with the balance after it.
 */
class WalletService
{
    public function credit(User $user, int $paise, string $description, ?Model $source = null, string $type = 'credit', ?int $by = null): WalletTransaction
    {
        if ($paise <= 0) throw new \InvalidArgumentException('Credit must be positive');
        return $this->move($user, $paise, $description, $source, $type, $by);
    }

    /** @throws InsufficientBalance */
    public function debit(User $user, int $paise, string $description, ?Model $source = null, string $type = 'debit', ?int $by = null): WalletTransaction
    {
        if ($paise <= 0) throw new \InvalidArgumentException('Debit must be positive');
        return $this->move($user, -$paise, $description, $source, $type, $by);
    }

    private function move(User $user, int $signed, string $description, ?Model $source, string $type, ?int $by): WalletTransaction
    {
        $user->walletModel(); // make sure it exists
        return DB::transaction(function () use ($user, $signed, $description, $source, $type, $by) {
            /** @var Wallet $w */
            $w = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            $new = $w->balance_paise + $signed;
            if ($signed < 0 && $new < 0) {
                throw new InsufficientBalance('Insufficient wallet balance');
            }
            $w->balance_paise = $new;
            $w->save();
            return WalletTransaction::create([
                'wallet_id' => $w->id,
                'txn_id' => 'TXN' . strtoupper(Str::random(12)),
                'type' => $type,
                'amount_paise' => $signed,
                'balance_after_paise' => $new,
                'description' => mb_substr($description, 0, 250),
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'created_by' => $by,
            ]);
        });
    }

    public function balance(User $user): int
    {
        return (int) Wallet::where('user_id', $user->id)->value('balance_paise');
    }
}
