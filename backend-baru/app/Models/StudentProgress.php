<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'material_id',
        'material_video_id',
        'exercise_id',
        'quiz_id',
        'progress_type',
        'score',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function materialVideo()
    {
        return $this->belongsTo(MaterialVideo::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
