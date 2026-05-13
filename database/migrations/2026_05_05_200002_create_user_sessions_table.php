<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('session_token', 36)->unique();
            $table->char('visitor_key', 36)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent()->index();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('entry_page', 255)->nullable();
            $table->enum('device', ['mobile', 'desktop', 'tablet', 'unknown'])->default('unknown');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
