<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();

            $table->string('vmos_order_id')->nullable();  // orderId from createEmailOrder, used to re-query getEmailOrder
            $table->string('email');
            $table->string('password')->nullable();

            // Latest verification code pulled from getEmailOrder, kept alongside
            // the full raw entry since VMOS's exact response shape for this
            // endpoint isn't published — see EmailAccountProvisioner.
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
        Schema::dropIfExists('email_accounts');
    }
};
