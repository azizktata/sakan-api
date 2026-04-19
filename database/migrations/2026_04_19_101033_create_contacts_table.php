<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('user_id')    // auteur du message (si connecté)
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false); // lu par le propriétaire du bien
            $table->timestamps();

            $table->index(['property_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};