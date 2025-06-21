<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add missing fields to quizzes table
        Schema::table('quizzes', function (Blueprint $table) {
            if (!Schema::hasColumn('quizzes', 'difficulty_level')) {
                $table->integer('difficulty_level')->default(1)->after('description');
            }
            if (!Schema::hasColumn('quizzes', 'time_limit')) {
                $table->integer('time_limit')->nullable()->after('difficulty_level'); // in minutes
            }
        });

        // Add missing fields to quiz_options table
        Schema::table('quiz_options', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_options', 'order')) {
                $table->integer('order')->default(1)->after('is_correct');
            }
        });
    }

    public function down()
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'difficulty_level')) {
                $table->dropColumn('difficulty_level');
            }
            if (Schema::hasColumn('quizzes', 'time_limit')) {
                $table->dropColumn('time_limit');
            }
        });

        Schema::table('quiz_options', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_options', 'order')) {
                $table->dropColumn('order');
            }
        });
    }
};
