<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('visitor_key', 36);        // UUID v4
            $table->string('session_bucket', 20);   // YYYY-MM-DD HH:00
            $table->char('unique_key', 64);          // SHA-256(property_id+visitor_key+session_bucket)
            $table->enum('source', ['direct', 'listing', 'map'])->default('direct');
            $table->enum('device', ['mobile', 'desktop', 'tablet', 'unknown'])->default('unknown');
            $table->char('user_agent_hash', 64)->nullable();
            $table->char('ip_hash', 64);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->unique('unique_key');
            $table->index('property_id');
            $table->index('visitor_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_views');
    }
};
