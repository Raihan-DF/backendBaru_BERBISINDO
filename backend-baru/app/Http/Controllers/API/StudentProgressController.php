<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\StudentProgress;
use Illuminate\Http\Request;

class StudentProgressController extends Controller
{
    public function index(Request $request)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userId = $request->user()->id;

        // Get overall progress statistics
        $totalMaterials = Material::where('is_published', true)->count();
        $completedMaterials = StudentProgress::where('user_id', $userId)
            ->where('progress_type', 'material')
            ->where('is_completed', true)
            ->count();

        $totalExercises = Exercise::where('is_published', true)->count();
        $completedExercises = StudentProgress::where('user_id', $userId)
            ->where('progress_type', 'exercise')
            ->where('is_completed', true)
            ->count();

        $totalQuizzes = Quiz::where('is_published', true)->count();
        $completedQuizzes = StudentProgress::where('user_id', $userId)
            ->where('progress_type', 'quiz')
            ->where('is_completed', true)
            ->count();

        // Calculate average quiz score
        $quizScores = StudentProgress::where('user_id', $userId)
            ->where('progress_type', 'quiz')
            ->whereNotNull('score')
            ->pluck('score');

        $averageQuizScore = $quizScores->isEmpty() ? 0 : $quizScores->avg();

        // Get recent progress
        $recentProgress = StudentProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->with(['material', 'exercise', 'quiz'])
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'materials' => [
                'total' => $totalMaterials,
                'completed' => $completedMaterials,
                'percentage' => $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 0,
            ],
            'exercises' => [
                'total' => $totalExercises,
                'completed' => $completedExercises,
                'percentage' => $totalExercises > 0 ? round(($completedExercises / $totalExercises) * 100) : 0,
            ],
            'quizzes' => [
                'total' => $totalQuizzes,
                'completed' => $completedQuizzes,
                'percentage' => $totalQuizzes > 0 ? round(($completedQuizzes / $totalQuizzes) * 100) : 0,
                'average_score' => round($averageQuizScore),
            ],
            'recent_progress' => $recentProgress,
        ]);
    }

    public function materials(Request $request)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userId = $request->user()->id;

        // Get all published materials with progress information
        $materials = Material::where('is_published', true)
            ->with(['videos'])
            ->get();

        $materialsWithProgress = $materials->map(function ($material) use ($userId) {
            $progress = StudentProgress::where('user_id', $userId)
                ->where('material_id', $material->id)
                ->where('progress_type', 'material')
                ->first();

            $videoProgress = StudentProgress::where('user_id', $userId)
                ->where('material_id', $material->id)
                ->where('progress_type', 'material_video')
                ->where('is_completed', true)
                ->count();

            $totalVideos = $material->videos->count();
            $progressPercentage = $totalVideos > 0 ?
                round(($videoProgress / $totalVideos) * 100) : 0;

            return [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'thumbnail' => $material->thumbnail,
                'difficulty_level' => $material->difficulty_level,
                'is_completed' => $progress ? $progress->is_completed : false,
                'completed_at' => $progress ? $progress->completed_at : null,
                'progress_percentage' => $progressPercentage,
                'total_videos' => $totalVideos,
                'completed_videos' => $videoProgress,
            ];
        });

        return response()->json($materialsWithProgress);
    }

    public function exercises(Request $request)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userId = $request->user()->id;

        // Get all published exercises with progress information
        $exercises = Exercise::where('is_published', true)
            ->with(['material'])
            ->get();

        $exercisesWithProgress = $exercises->map(function ($exercise) use ($userId) {
            $progress = StudentProgress::where('user_id', $userId)
                ->where('exercise_id', $exercise->id)
                ->where('progress_type', 'exercise')
                ->first();

            return [
                'id' => $exercise->id,
                'title' => $exercise->title,
                'description' => $exercise->description,
                'material_id' => $exercise->material_id,
                'material_title' => $exercise->material ? $exercise->material->title : null,
                'difficulty_level' => $exercise->difficulty_level,
                'is_completed' => $progress ? $progress->is_completed : false,
                'completed_at' => $progress ? $progress->completed_at : null,
            ];
        });

        return response()->json($exercisesWithProgress);
    }

    public function quizzes(Request $request)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userId = $request->user()->id;

        // Get all published quizzes with progress information
        $quizzes = Quiz::where('is_published', true)
            ->with(['material'])
            ->get();

        $quizzesWithProgress = $quizzes->map(function ($quiz) use ($userId) {
            $progress = StudentProgress::where('user_id', $userId)
                ->where('quiz_id', $quiz->id)
                ->where('progress_type', 'quiz')
                ->first();

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'material_id' => $quiz->material_id,
                'material_title' => $quiz->material ? $quiz->material->title : null,
                'passing_score' => $quiz->passing_score,
                'is_completed' => $progress ? $progress->is_completed : false,
                'score' => $progress ? $progress->score : null,
                'completed_at' => $progress ? $progress->completed_at : null,
                'passed' => $progress ? ($progress->score >= $quiz->passing_score) : false,
            ];
        });

        return response()->json($quizzesWithProgress);
    }
}
