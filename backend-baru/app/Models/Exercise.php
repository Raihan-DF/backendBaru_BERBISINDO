<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'material_id',
        'created_by',
        'difficulty_level',
        'is_published',
    ];

    protected $appends = ['total_questions', 'total_points'];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(ExerciseQuestion::class)->orderBy('order');
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class);
    }

    // Accessor untuk total questions
    public function getTotalQuestionsAttribute()
    {
        return $this->questions()->count();
    }

    // Accessor untuk total points
    public function getTotalPointsAttribute()
    {
        return $this->questions()->sum('points');
    }

    // Scope untuk published exercises
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    // Scope untuk exercises by creator
    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }
}
