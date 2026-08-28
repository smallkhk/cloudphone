<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // deposit | purchase | refund | adjustment
            // Signed — positive credits the balance, negative debits it.
            $table->decimal('amount', 12, 2);
            // Snapshot of the balance right after this entry, so the ledger is
            // self-auditing even if users.balance is ever recomputed by hand.
            $table->decimal('balance_after', 12, 2);
            $table->string('description')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
