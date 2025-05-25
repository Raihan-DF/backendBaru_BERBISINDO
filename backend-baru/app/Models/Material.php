<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'created_by',
        'difficulty_level',
        'is_published',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function videos()
    {
        return $this->hasMany(MaterialVideo::class)->orderBy('order');
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class);
    }
}
