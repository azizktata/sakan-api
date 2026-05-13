<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views_total')->default(0);
            $table->unsignedInteger('properties_published')->default(0);
            $table->unsignedInteger('contacts_count')->default(0);
            $table->decimal('demand_supply_ratio', 8, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['location_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_stats_daily');
    }
};
