<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_identities', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_key', 36)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('stitched_at');
            $table->unique(['visitor_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_identities');
    }
};
