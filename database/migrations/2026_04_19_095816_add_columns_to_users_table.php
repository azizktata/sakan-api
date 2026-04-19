<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // make password nullable for OAuth users
            $table->string('password')->nullable()->change();
            $table->enum('role', ['particulier', 'agent', 'admin'])->default('particulier');
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('google_id')->nullable()->unique(); // OAuth Google
            $table->string('provider')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'avatar', 'google_id', 'provider']);
        });
    }
};
