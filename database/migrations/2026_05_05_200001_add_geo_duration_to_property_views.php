<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_views', function (Blueprint $table) {
            $table->char('view_id', 36)->nullable()->after('id');
            $table->string('country', 100)->nullable()->after('ip_hash');
            $table->string('city_geo', 100)->nullable()->after('country');
            $table->unsignedInteger('duration_seconds')->nullable()->after('city_geo');
            $table->index('view_id');
        });
    }

    public function down(): void
    {
        Schema::table('property_views', function (Blueprint $table) {
            $table->dropIndex(['view_id']);
            $table->dropColumn(['view_id', 'country', 'city_geo', 'duration_seconds']);
        });
    }
};
