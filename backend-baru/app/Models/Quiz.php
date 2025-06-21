<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'material_id',
        'created_by',
        'difficulty_level',
        'time_limit', // in minutes
        'passing_score',
        'is_published',
    ];

    protected $appends = ['total_questions', 'total_points'];

    protected $casts = [
        'is_published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class, 'quiz_id')->where('progress_type', 'quiz');
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

    // User-specific methods untuk frontend
    public function getUserProgress($userId)
    {
        return $this->progress()->where('user_id', $userId)->latest()->first();
    }

    public function getUserAttemptCount($userId)
    {
        return $this->progress()->where('user_id', $userId)->count();
    }

    public function getUserBestScore($userId)
    {
        $bestAttempt = $this->progress()->where('user_id', $userId)->orderBy('score', 'desc')->first();
        return $bestAttempt ? $bestAttempt->score : null;
    }

    public function isCompletedByUser($userId)
    {
        return $this->progress()->where('user_id', $userId)->where('is_completed', true)->exists();
    }

    public function canUserAttempt($userId)
    {
        // Check if user has active (unfinished) attempt
        $activeAttempt = $this->getActiveAttempt($userId);
        if ($activeAttempt) {
            return true; // Can continue existing attempt
        }

        // For now, allow unlimited attempts
        // You can add max_attempts logic here later
        return true;
    }

    public function getLastAttemptByUser($userId)
    {
        return $this->progress()->where('user_id', $userId)->latest()->first();
    }

    // Get active (unfinished) attempt
    public function getActiveAttempt($userId)
    {
        return $this->progress()
            ->where('user_id', $userId)
            ->where('is_completed', false)
            ->whereNotNull('started_at')
            ->first();
    }

    // Check if time limit exceeded for active attempt
    public function isTimeExceeded($userId)
    {
        if (!$this->time_limit) {
            return false; // No time limit
        }

        $activeAttempt = $this->getActiveAttempt($userId);
        if (!$activeAttempt || !$activeAttempt->started_at) {
            return false;
        }

        return $activeAttempt->isTimeExceeded($this->time_limit);
    }

    // Get remaining time in seconds for active attempt
    public function getRemainingTime($userId)
    {
        if (!$this->time_limit) {
            return null; // No time limit
        }

        $activeAttempt = $this->getActiveAttempt($userId);
        if (!$activeAttempt || !$activeAttempt->started_at) {
            return $this->time_limit * 60; // Full time in seconds
        }

        return $activeAttempt->getRemainingTime($this->time_limit);
    }
}
