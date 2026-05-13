<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimation_logs', function (Blueprint $table) {
            $table->char('estimation_id', 36)->nullable()->after('id')->index();
            $table->enum('user_opinion', ['too_high', 'correct', 'too_low'])->nullable()->after('ip');
            $table->timestamp('feedback_at')->nullable()->after('user_opinion');
        });
    }

    public function down(): void
    {
        Schema::table('estimation_logs', function (Blueprint $table) {
            $table->dropColumn(['estimation_id', 'user_opinion', 'feedback_at']);
        });
    }
};
