<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();

            $table->string('vmos_order_id')->nullable();  // orderId from createSmsOrder, used to re-query getSmsOrder/getSmsCode
            $table->string('phone_number');
            $table->string('country_code', 8)->nullable();

            // Latest SMS verification code pulled from getSmsCode, kept alongside
            // the full raw entry since VMOS's response shape for this endpoint
            // isn't published — see PhoneNumberProvisioner.
            $table->string('latest_code')->nullable();
            $table->timestamp('code_fetched_at')->nullable();
            $table->json('raw_payload')->nullable();

            $table->string('status')->default('active'); // active, expired
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
