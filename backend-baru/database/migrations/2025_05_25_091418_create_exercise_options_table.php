// database/migrations/xxxx_create_exercise_options_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('exercise_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_question_id')->constrained()->onDelete('cascade');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->integer('order')->default(1);
            $table->timestamps();

            $table->index(['exercise_question_id', 'order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('exercise_options');
    }
};
