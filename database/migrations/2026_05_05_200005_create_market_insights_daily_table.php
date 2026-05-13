<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_insights_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete()->index();
            $table->date('date');
            $table->unsignedInteger('searches_count')->default(0);
            $table->unsignedInteger('searches_zero_results')->default(0);
            $table->unsignedInteger('views_total')->default(0);
            $table->unsignedInteger('properties_published')->default(0);
            $table->decimal('avg_price', 12, 2)->nullable();
            $table->unsignedInteger('contacts_count')->default(0);
            $table->decimal('avg_time_to_contact_hours', 8, 2)->nullable();
            $table->decimal('demand_index', 8, 4)->default(0);
            $table->decimal('attractiveness_score', 8, 4)->default(0);
            $table->decimal('liquidity_index', 8, 4)->default(0);
            $table->decimal('search_gap_index', 5, 4)->default(0);
            $table->timestamps();
            $table->unique(['location_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_insights_daily');
    }
};
