<?php
// database/migrations/xxxx_add_exercise_fields_to_student_progress_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->integer('max_score')->nullable()->after('score');
            $table->integer('attempt_count')->default(0)->after('max_score');
        });
    }

    public function down(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->dropColumn(['max_score', 'attempt_count']);
        });
    }
};
