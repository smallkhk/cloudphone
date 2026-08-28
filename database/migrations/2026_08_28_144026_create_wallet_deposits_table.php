<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency')->default('USDT');
            $table->string('network'); // TRC20 | BEP20
            $table->string('pay_to_address');
            $table->decimal('amount_crypto', 18, 8);
            $table->decimal('amount_usd', 12, 2);
            $table->string('tx_hash')->nullable();
            $table->string('status'); // awaiting_payment | submitted | confirmed | failed | expired
            $table->unsignedInteger('confirmations')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_deposits');
    }
};
