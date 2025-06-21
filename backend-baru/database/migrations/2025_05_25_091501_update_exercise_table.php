// database/migrations/xxxx_update_exercises_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('exercises', function (Blueprint $table) {
            // Hapus kolom video_path karena sekarang video diambil dari MaterialVideo
            $table->dropColumn(['video_path']);
        });
    }

    public function down()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('video_path')->nullable();
        });
    }
};
