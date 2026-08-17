<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 'vmos' (a VMOS residential proxy, bought alongside the device) or
            // 'custom' (the customer's own proxy). Null = no proxy add-on.
            $table->string('proxy_mode', 10)->nullable()->after('country_code');

            // Mode-specific details — VMOS good/region for 'vmos', ip/port/
            // account/password/proxy_name/proxy_type for 'custom'. Also carries
            // matched_proxy_id once a bought VMOS proxy has been positively
            // identified in the owned-proxy list, and the raw purchase response
            // for diagnosability.
            $table->json('proxy_config')->nullable();

            $table->decimal('proxy_price', 10, 2)->default(0);   // what the customer paid for it
            $table->decimal('proxy_cost_price', 10, 2)->default(0); // what VMOS charged us

            // pending -> purchased (vmos only) -> attached, or failed at any step.
            $table->string('proxy_status', 20)->nullable();
            $table->text('proxy_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['proxy_mode', 'proxy_config', 'proxy_price', 'proxy_cost_price', 'proxy_status', 'proxy_error']);
        });
    }
};
