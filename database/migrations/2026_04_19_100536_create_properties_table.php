<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('price');
            $table->enum('transaction_type', ['sale', 'rent']);
            $table->enum('property_type', ['apartment', 'villa', 'house', 'land', 'commercial', 'office']);
            $table->enum('status', ['draft', 'published', 'sold', 'rented'])->default('draft');
            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('surface')->nullable();      // m²
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->tinyInteger('floor')->nullable();                  // peut être négatif (sous-sol)
            $table->boolean('is_furnished')->default(false);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Index pour les filtres listing
            $table->index('transaction_type');
            $table->index('property_type');
            $table->index('status');
            $table->index('location_id');
            $table->index('price');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};