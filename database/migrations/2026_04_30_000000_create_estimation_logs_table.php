<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimation_logs', function (Blueprint $table) {
            $table->id();
            $table->json('input');
            $table->json('result')->nullable();
            $table->string('model_version', 32)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimation_logs');
    }
};
