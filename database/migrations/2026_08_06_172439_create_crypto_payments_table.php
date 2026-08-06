<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('currency', 16)->default('USDT');
            $table->string('network', 16)->default('TRC20'); // TRC20 / ERC20 / BEP20
            $table->string('pay_to_address');
            $table->decimal('amount_crypto', 24, 8);
            $table->decimal('amount_usd', 10, 2);

            $table->string('tx_hash')->nullable()->unique();

            // awaiting_payment -> submitted -> confirmed
            //                  -> failed / expired
            $table->string('status')->default('awaiting_payment');

            $table->unsignedInteger('confirmations')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_payments');
    }
};
