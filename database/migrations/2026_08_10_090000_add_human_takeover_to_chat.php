<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Set while a person is handling the conversation. The assistant
            // stays out of the way until it's cleared, so the customer never
            // gets a bot reply on top of a human one.
            $table->boolean('human_handling')->default(false)->after('needs_human');
            $table->foreignId('handled_by')->nullable()->after('human_handling')->constrained('users')->nullOnDelete();
            // Drives the unread badge in the admin nav.
            $table->timestamp('admin_read_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['human_handling', 'admin_read_at']);
        });
    }
};
