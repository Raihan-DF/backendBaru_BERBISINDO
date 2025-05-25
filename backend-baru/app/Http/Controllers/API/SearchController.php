<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Material;
use App\Models\Quiz;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');
        $user = $request->user();

        if (empty($query)) {
            return response()->json([
                'materials' => [],
                'exercises' => [],
                'quizzes' => [],
            ]);
        }

        // Search materials
        $materialsQuery = Material::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%");

        // Filter by published status for students
        if ($user->isStudent()) {
            $materialsQuery->where('is_published', true);
        }

        $materials = $materialsQuery->limit(5)->get();

        // Search exercises
        $exercisesQuery = Exercise::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%");

        // Filter by published status for students
        if ($user->isStudent()) {
            $exercisesQuery->where('is_published', true);
        }

        $exercises = $exercisesQuery->limit(5)->get();

        // Search quizzes
        $quizzesQuery = Quiz::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%");

        // Filter by published status for students
        if ($user->isStudent()) {
            $quizzesQuery->where('is_published', true);
        }

        $quizzes = $quizzesQuery->limit(5)->get();

        return response()->json([
            'materials' => $materials,
            'exercises' => $exercises,
            'quizzes' => $quizzes,
        ]);
    }
}
