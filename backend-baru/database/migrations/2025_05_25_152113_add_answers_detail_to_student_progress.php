<?php
// database/migrations/xxxx_add_answers_detail_to_student_progress.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->json('answers_detail')->nullable()->after('max_score');
            $table->timestamp('started_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->dropColumn(['answers_detail', 'started_at']);
        });
    }
};
