<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_proxies', function (Blueprint $table) {
            $table->string('label')->nullable()->after('source'); // a friendly name, like VMOS's own "Proxy Name" field
            $table->text('remarks')->nullable()->after('proxy_type');
        });
    }

    public function down(): void
    {
        Schema::table('customer_proxies', function (Blueprint $table) {
            $table->dropColumn(['label', 'remarks']);
        });
    }
};
