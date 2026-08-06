<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloud_instance_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('vmos_task_id')->nullable()->index();
            $table->string('type', 32); // restart, reset, screenshot, ...

            // pending -> completed / failed (mirrors VMOS taskStatus, simplified)
            $table->string('status')->default('pending');
            $table->json('result')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_tasks');
    }
};
