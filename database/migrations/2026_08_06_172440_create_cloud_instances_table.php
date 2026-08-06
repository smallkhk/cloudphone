<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sku_id')->nullable()->constrained()->nullOnDelete();

            $table->string('pad_code')->nullable()->unique(); // VMOS instance code, set once provisioned
            $table->string('equipment_id')->nullable();
            $table->string('nickname')->nullable();

            $table->integer('pad_status')->nullable();  // VMOS cvmStatus / padStatus code, cached
            $table->boolean('online')->default(false);
            $table->string('screenshot_url')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('expires_at')->nullable(); // signExpirationTime

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_instances');
    }
};
