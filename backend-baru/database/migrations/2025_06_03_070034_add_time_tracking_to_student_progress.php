<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('student_progress', function (Blueprint $table) {
            if (!Schema::hasColumn('student_progress', 'time_taken')) {
                $table->integer('time_taken')->nullable()->after('completed_at'); // in seconds
            }
        });
    }

    public function down()
    {
        Schema::table('student_progress', function (Blueprint $table) {
            if (Schema::hasColumn('student_progress', 'time_taken')) {
                $table->dropColumn('time_taken');
            }
        });
    }
};
