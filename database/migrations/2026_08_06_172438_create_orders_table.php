<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // customer-facing order number

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->boolean('auto_renew')->default(true);
            $table->string('country_code', 8)->nullable();

            // pending_payment -> paid -> provisioning -> completed
            //                 -> failed / canceled / expired
            $table->string('status')->default('pending_payment');

            $table->string('vmos_order_id')->nullable();   // orderId from createMoneyOrder
            $table->string('vmos_equipment_id')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
