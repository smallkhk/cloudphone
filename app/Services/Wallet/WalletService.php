<?php

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place a user's balance is allowed to change. Every change locks
 * the user row for the duration of the update and writes a WalletTransaction
 * ledger entry in the same transaction, so concurrent requests (e.g. two
 * checkout attempts paid from the same balance) can't race each other into
 * an inconsistent balance.
 */
class WalletService
{
    public function credit(User $user, float $amount, string $type, string $description, ?Order $order = null, ?WalletDeposit $deposit = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $order, $deposit) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $newBalance = round((float) $locked->balance + $amount, 2);

            // update() is guarded by $fillable, which deliberately excludes
            // balance — it must never be mass-assignable from request input,
            // only ever changed through this service.
            $locked->forceFill(['balance' => $newBalance])->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'order_id' => $order?->id,
                'wallet_deposit_id' => $deposit?->id,
            ]);
        });
    }

    /**
     * @throws RuntimeException if the balance can't cover the debit
     */
    public function debit(User $user, float $amount, string $type, string $description, ?Order $order = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $order) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ((float) $locked->balance < $amount) {
                throw new RuntimeException('Insufficient balance.');
            }

            $newBalance = round((float) $locked->balance - $amount, 2);

            // update() is guarded by $fillable, which deliberately excludes
            // balance — it must never be mass-assignable from request input,
            // only ever changed through this service.
            $locked->forceFill(['balance' => $newBalance])->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'order_id' => $order?->id,
            ]);
        });
    }
}
