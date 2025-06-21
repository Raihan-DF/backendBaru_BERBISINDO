<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_question_id',
        'option_text',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question()
    {
        return $this->belongsTo(ExerciseQuestion::class, 'exercise_question_id');
    }

    // Scope untuk correct options
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }
}
