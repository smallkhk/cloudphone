<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_numbers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();

            // VMOS's own record id for this number — used for autoRenew/bind/
            // bind-status calls, which all key off it rather than the number.
            $table->unsignedInteger('vmos_number_id')->nullable();
            $table->string('number')->nullable(); // E.164, no leading "+"
            $table->string('country_code', 8)->nullable();

            // Purchase is async on VMOS's side — see CloudNumberProvisioner.
            $table->string('purchase_client_token')->nullable();
            $table->string('purchase_status')->default('PROCESSING'); // mirrors VMOS's own status values
            $table->text('purchase_error')->nullable();

            $table->boolean('auto_renew')->default(true);
            $table->string('row_version')->nullable(); // VMOS's optimistic-lock token for autoRenew updates
            $table->string('vmos_status')->nullable(); // normal | soon | expired | disabled
            $table->timestamp('expire_time')->nullable();

            // Binding a number to a device is a separate, explicit, async
            // step (forces a real device restart) — see CloudNumberController.
            $table->string('bound_pad_code')->nullable();
            $table->string('bind_state')->nullable(); // NONE | RUNNING | SUCCESS | FAILED
            $table->string('bind_request_id')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_numbers');
    }
};
