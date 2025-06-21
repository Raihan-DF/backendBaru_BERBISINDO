<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'material_video_id',
        'question',
        'question_type',
        'points',
        'order',
    ];

    protected $with = ['materialVideo', 'options'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function materialVideo()
    {
        return $this->belongsTo(MaterialVideo::class);
    }

    public function options()
    {
        return $this->hasMany(QuizOption::class)->orderBy('order');
    }

    public function correctOption()
    {
        return $this->hasOne(QuizOption::class)->where('is_correct', true);
    }

    // Get correct option
    public function getCorrectOption()
    {
        return $this->options()->where('is_correct', true)->first();
    }

    // Check if has correct option
    public function hasCorrectOption()
    {
        return $this->options()->where('is_correct', true)->exists();
    }

    // Accessor untuk video info dari MaterialVideo
    public function getVideoInfoAttribute()
    {
        return $this->materialVideo ? $this->materialVideo->video_info : null;
    }

    public function getVideoUrlAttribute()
    {
        return $this->materialVideo ? $this->materialVideo->video_url : null;
    }

    public function getStreamUrlAttribute()
    {
        return $this->materialVideo ? $this->materialVideo->stream_url : null;
    }
}
