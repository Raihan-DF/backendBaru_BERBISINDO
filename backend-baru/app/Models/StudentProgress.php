<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StudentProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'material_id',
        'exercise_id',
        'quiz_id',
        'progress_type',
        'score',
        'max_score',
        'answers_detail',
        'is_completed',
        'attempt_count',
        'started_at',
        'completed_at',
        'time_taken', // in seconds
    ];

    protected $casts = [
        'answers_detail' => 'array',
        'is_completed' => 'boolean',
        'started_at' => 'datetime',
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

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    // Add answer to progress
    public function addAnswer($questionId, $selectedOptionId, $isCorrect, $pointsEarned)
    {
        $answers = $this->answers_detail ?? [];
        $answers[$questionId] = [
            'selected_option_id' => $selectedOptionId,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'answered_at' => now()->toISOString(),
        ];
        $this->answers_detail = $answers;
        $this->save();
    }

    // Get correct answers count
    public function getCorrectAnswersCount()
    {
        $answers = $this->answers_detail ?? [];
        return collect($answers)->where('is_correct', true)->count();
    }

    // Calculate time taken in seconds
    public function calculateTimeTaken()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }

    // Get time taken in human readable format
    public function getFormattedTimeTaken()
    {
        $seconds = $this->time_taken;
        if (!$seconds) return null;

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%02d:%02d', $minutes, $secs);
        }
    }

    // Check if time limit exceeded
    public function isTimeExceeded($timeLimitMinutes)
    {
        if (!$timeLimitMinutes || !$this->started_at) {
            return false;
        }

        $timeElapsed = now()->diffInMinutes($this->started_at);
        return $timeElapsed > $timeLimitMinutes;
    }

    // Get remaining time in seconds
    public function getRemainingTime($timeLimitMinutes)
    {
        if (!$timeLimitMinutes || !$this->started_at) {
            return null;
        }

        $timeElapsed = now()->diffInSeconds($this->started_at);
        $totalTimeInSeconds = $timeLimitMinutes * 60;
        $remainingTime = $totalTimeInSeconds - $timeElapsed;

        return max(0, $remainingTime);
    }

    // Mark as completed and calculate time taken
    public function markCompleted()
    {
        $this->completed_at = now();
        $this->is_completed = true;

        if ($this->started_at) {
            $this->time_taken = $this->calculateTimeTaken();
        }

        $this->save();
        return $this;
    }

    // Start progress tracking
    public function startProgress()
    {
        if (!$this->started_at) {
            $this->started_at = now();
            $this->save();
        }
        return $this;
    }

    // For Teachers - Get all students progress
    public function getAllStudentsProgress(Request $request)
    {
        try {
            // Check if user exists and has teacher role
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Check if user is a teacher
            if (!isset($user->role) || $user->role !== 'teacher') {
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Get all student progress records
            $studentProgress = StudentProgress::with('user')->get();

            // Return the student progress data
            return response()->json($studentProgress, 200);

        } catch (\Exception $e) {
            // Log the error
            // Log::error('Error in getAllStudentsProgress', [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);

            // Return an error response
            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    // For Teachers - Get overview statistics for dashboard
    public function getAllStudentsOverview(Request $request)
    {
        try {
            // Check if user exists and has teacher role
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Check if user is a teacher
            if (!isset($user->role) || $user->role !== 'teacher') {
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Get all students
            $students = User::where('role', 'student')->get();
            $totalStudents = $students->count();

            if ($totalStudents === 0) {
                return response()->json([
                    'total_students' => 0,
                    'active_students' => 0,
                    'materials' => ['average_percentage' => 0],
                    'exercises' => ['average_percentage' => 0],
                    'quizzes' => ['average_score' => 0]
                ]);
            }

            // Count active students (those with recent activity)
            $activeStudents = StudentProgress::where('is_completed', true)
                ->where('completed_at', '>=', now()->subWeek())
                ->distinct('user_id')
                ->count('user_id');

            // Calculate averages
            $totalMaterials = Material::where('is_published', true)->count();
            $totalExercises = Exercise::where('is_published', true)->count();
            $totalQuizzes = Quiz::where('is_published', true)->count();

            $materialProgressSum = 0;
            $exerciseProgressSum = 0;
            $quizScoreSum = 0;

            foreach ($students as $student) {
                // Material progress
                $completedMaterials = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'material')
                    ->where('is_completed', true)
                    ->count();
                $materialProgressSum += $totalMaterials > 0 ? ($completedMaterials / $totalMaterials) * 100 : 0;

                // Exercise progress
                $completedExercises = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'exercise')
                    ->where('is_completed', true)
                    ->count();
                $exerciseProgressSum += $totalExercises > 0 ? ($completedExercises / $totalExercises) * 100 : 0;

                // Quiz average score
                $quizScores = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'quiz')
                    ->whereNotNull('score')
                    ->pluck('score');
                $quizScoreSum += $quizScores->isEmpty() ? 0 : $quizScores->avg();
            }

            return response()->json([
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'materials' => [
                    'average_percentage' => round($materialProgressSum / $totalStudents)
                ],
                'exercises' => [
                    'average_percentage' => round($exerciseProgressSum / $totalStudents)
                ],
                'quizzes' => [
                    'average_score' => round($quizScoreSum / $totalStudents)
                ]
            ]);

        } catch (\Exception $e) {
            // Log::error('Error in getAllStudentsOverview', [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }
}
