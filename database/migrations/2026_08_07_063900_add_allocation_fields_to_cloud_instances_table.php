<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_instances', function (Blueprint $table) {
            // Admin-allocated devices aren't tied to a customer order, so user_id
            // needs to be nullable for stock that isn't assigned to anyone yet.
            $table->foreignId('user_id')->nullable()->change();

            $table->timestamp('allocated_at')->nullable()->after('expires_at');
            $table->foreignId('allocated_by')->nullable()->after('allocated_at')
                ->constrained('users')->nullOnDelete();
            $table->string('allocation_note', 500)->nullable()->after('allocated_by');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('allocated_by');
            $table->dropColumn(['allocated_at', 'allocation_note']);
        });
    }
};
