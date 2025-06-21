<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'material_video_id',
        'question',
        'points',
        'order',
    ];

    protected $with = ['options', 'materialVideo'];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    public function materialVideo()
    {
        return $this->belongsTo(MaterialVideo::class);
    }

    public function options()
    {
        return $this->hasMany(ExerciseOption::class)->orderBy('order');
    }

    public function correctOption()
    {
        return $this->hasOne(ExerciseOption::class)->where('is_correct', true);
    }

    // Scope untuk questions by exercise
    public function scopeByExercise($query, $exerciseId)
    {
        return $query->where('exercise_id', $exerciseId);
    }
}
