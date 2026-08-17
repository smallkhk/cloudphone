<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive only — this table has hit MySQL's 1071 "key too long" error
        // before (see CLAUDE.md), so existing indexed columns are left alone.
        Schema::table('skus', function (Blueprint $table) {
            $table->string('type', 20)->default('cloud_phone')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
