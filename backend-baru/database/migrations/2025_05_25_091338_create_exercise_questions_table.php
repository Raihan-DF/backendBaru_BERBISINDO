// database/migrations/xxxx_create_exercise_questions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('exercise_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_video_id')->constrained()->onDelete('cascade');
            $table->text('question');
            $table->integer('points')->default(10);
            $table->integer('order')->default(1);
            $table->timestamps();

            $table->index(['exercise_id', 'order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('exercise_questions');
    }
};
