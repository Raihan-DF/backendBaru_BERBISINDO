<?php
// database/migrations/xxxx_update_quiz_questions_use_material_video.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            // Tambah foreign key ke material_videos
            $table->foreignId('material_video_id')->nullable()->constrained()->onDelete('cascade');

            // Hapus kolom video yang lama (optional - bisa dilakukan bertahap)
            // $table->dropColumn(['video_path']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropForeign(['material_video_id']);
            $table->dropColumn('material_video_id');
            // $table->string('video_path')->nullable();
        });
    }
};
