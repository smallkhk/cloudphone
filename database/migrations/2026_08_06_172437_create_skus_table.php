<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skus', function (Blueprint $table) {
            $table->id();

            // VMOS getCloudGoodList identifiers.
            $table->unsignedInteger('vmos_config_id');   // "configId" - device configuration
            $table->unsignedInteger('vmos_good_id');     // "id" within goodTimes - passed as goodId to createMoneyOrder
            $table->string('android_version', 10);

            $table->string('name');                      // configName, e.g. "Samsung Galaxy A53"
            $table->string('config_model')->nullable();   // configModel, e.g. "Samsung"
            $table->string('duration_label')->nullable(); // showContent, e.g. "1 day", "1 month"
            $table->unsignedInteger('duration_minutes');  // goodTime

            $table->decimal('vmos_cost_price', 10, 2);    // currentPrice from VMOS (our cost)
            $table->decimal('price', 10, 2);              // what we charge the customer (set by admin, markup)

            $table->string('default_country_code', 8)->nullable();
            $table->boolean('sell_out')->default(false);  // synced from VMOS sellOutFlag
            $table->boolean('active')->default(true);     // whether we sell it on our storefront
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('raw_payload')->nullable();       // last synced VMOS payload, for reference/debugging
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->unique(['vmos_good_id', 'android_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};
