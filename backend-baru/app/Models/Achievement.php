<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'icon',
        'achievement_type',
        'required_count',
        'points',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'student_achievements')
            ->withPivot('achieved_at')
            ->withTimestamps();
    }
}
