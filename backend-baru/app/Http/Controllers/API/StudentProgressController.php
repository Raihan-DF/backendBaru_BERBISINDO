<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\StudentProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentProgressController extends Controller
{
    // For Teachers - Get all students progress overview
    public function getAllStudentsProgress(Request $request)
    {
        $user = $request->user();

        // Log request for debugging
        Log::info('getAllStudentsProgress called', [
            'user_id' => $user ? $user->id : 'null',
            'roles' => $user ? $user->getRoleSlugs() : 'null',
            'user_email' => $user ? $user->email : 'null',
            'token' => $request->bearerToken() ? 'exists' : 'missing',
            'token_abilities' => $user ? $user->currentAccessToken()->abilities ?? [] : 'no token'
        ]);

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user is a teacher
        if (!$user->isTeacher()) {
            Log::warning('Unauthorized access attempt to getAllStudentsProgress', [
                'user_id' => $user->id,
                'roles' => $user->getRoleSlugs(),
                'expected_role' => 'teacher'
            ]);
            return response()->json([
                'message' => 'Unauthorized - Teacher access required',
                'user_roles' => $user->getRoleSlugs(),
                'required_role' => 'teacher'
            ], 403);
        }

        // Get all students (users with student role)
        $students = User::whereHas('roles', function($query) {
            $query->where('slug', 'student');
        })->get();

        $studentsProgress = [];

        foreach ($students as $student) {
            // Get materials progress
            $materialsProgress = $this->getMaterialsProgress($student->id);

            // Get exercises progress
            $exercisesProgress = $this->getExercisesProgress($student->id);

            // Get quizzes progress
            $quizzesProgress = $this->getQuizzesProgress($student->id);

            // Get last activity
            $lastActivity = $this->getLastActivity($student->id);

            $studentsProgress[] = [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email
                ],
                'materials' => $materialsProgress,
                'exercises' => $exercisesProgress,
                'quizzes' => $quizzesProgress,
                'last_activity' => $lastActivity
            ];
        }

        return response()->json($studentsProgress);
    }

    /**
     * Get detailed progress for a specific student (for teacher)
     */
    public function getStudentDetailProgress(Request $request, $studentId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user is a teacher
        if (!$user->isTeacher()) {
            return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
        }

        // Check if student exists
        $student = User::find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        // Get overall progress statistics
        $totalMaterials = Material::where('is_published', true)->count();
        $completedMaterials = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'material')
            ->where('is_completed', true)
            ->count();

        $totalExercises = Exercise::where('is_published', true)->count();
        $completedExercises = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'exercise')
            ->where('is_completed', true)
            ->count();

        $totalQuizzes = Quiz::where('is_published', true)->count();
        $completedQuizzes = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'quiz')
            ->where('is_completed', true)
            ->count();

        // Calculate average quiz score
        $quizScores = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'quiz')
            ->whereNotNull('score')
            ->pluck('score');

        $averageQuizScore = $quizScores->isEmpty() ? 0 : $quizScores->avg();

        // Get recent progress
        $recentProgress = StudentProgress::where('user_id', $studentId)
            ->where('is_completed', true)
            ->with(['material', 'exercise', 'quiz'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        // Get detailed progress for each category
        $materialProgress = $this->getStudentMaterialProgress($studentId);
        $exerciseProgress = $this->getStudentExerciseProgress($studentId);
        $quizProgress = $this->getStudentQuizProgress($studentId);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email
            ],
            'overview' => [
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
            ],
            'recent_progress' => $recentProgress->map(function ($progress) {
                return [
                    'id' => $progress->id,
                    'progress_type' => $progress->progress_type,
                    'completed_at' => $progress->completed_at,
                    'material' => $progress->material ? ['title' => $progress->material->title] : null,
                    'exercise' => $progress->exercise ? ['title' => $progress->exercise->title] : null,
                    'quiz' => $progress->quiz ? ['title' => $progress->quiz->title] : null,
                    'score' => $progress->score,
                ];
            }),
            'material_progress' => $materialProgress,
            'exercise_progress' => $exerciseProgress,
            'quiz_progress' => $quizProgress,
        ]);
    }

    /**
     * Get student's own progress (for student) - NEW METHOD
     */
    public function getMyProgress(Request $request)
    {
        try {
            $user = $request->user();

            Log::info('getMyProgress called', [
                'user_id' => $user ? $user->id : 'null',
                'roles' => $user ? $user->getRoleSlugs() : 'null',
                'user_email' => $user ? $user->email : 'null'
            ]);

            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Check if user is a student
            if (!$user->isStudent()) {
                Log::warning('Unauthorized access attempt to getMyProgress', [
                    'user_id' => $user->id,
                    'roles' => $user->getRoleSlugs(),
                    'expected_role' => 'student'
                ]);
                return response()->json(['message' => 'Unauthorized - Student access required'], 403);
            }

            $userId = $user->id;

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
                ->limit(10)
                ->get();

            // Get detailed progress for each category
            $materialProgress = $this->getStudentMaterialProgress($userId);
            $exerciseProgress = $this->getStudentExerciseProgress($userId);
            $quizProgress = $this->getStudentQuizProgress($userId);

            return response()->json([
                'overview' => [
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
                ],
                'recent_progress' => $recentProgress->map(function ($progress) {
                    return [
                        'id' => $progress->id,
                        'progress_type' => $progress->progress_type,
                        'completed_at' => $progress->completed_at,
                        'material' => $progress->material ? ['title' => $progress->material->title] : null,
                        'exercise' => $progress->exercise ? ['title' => $progress->exercise->title] : null,
                        'quiz' => $progress->quiz ? ['title' => $progress->quiz->title] : null,
                        'score' => $progress->score,
                    ];
                }),
                'material_progress' => $materialProgress,
                'exercise_progress' => $exerciseProgress,
                'quiz_progress' => $quizProgress,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getMyProgress', [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Helper method to get materials progress - FIXED
     */
    private function getMaterialsProgress($studentId)
    {
        $totalMaterials = Material::where('is_published', true)->count();
        $completedMaterials = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'material')
            ->where('is_completed', true)
            ->count();

        $percentage = $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 0;

        return [
            'total' => $totalMaterials,
            'completed' => $completedMaterials,
            'percentage' => $percentage
        ];
    }

    /**
     * Helper method to get exercises progress - FIXED
     */
    private function getExercisesProgress($studentId)
    {
        $totalExercises = Exercise::where('is_published', true)->count();
        $completedExercises = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'exercise')
            ->where('is_completed', true)
            ->count();

        $percentage = $totalExercises > 0 ? round(($completedExercises / $totalExercises) * 100) : 0;

        return [
            'total' => $totalExercises,
            'completed' => $completedExercises,
            'percentage' => $percentage
        ];
    }

    /**
     * Helper method to get quizzes progress - FIXED
     */
    private function getQuizzesProgress($studentId)
    {
        $totalQuizzes = Quiz::where('is_published', true)->count();
        $completedQuizzes = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'quiz')
            ->where('is_completed', true)
            ->count();

        $percentage = $totalQuizzes > 0 ? round(($completedQuizzes / $totalQuizzes) * 100) : 0;

        // Calculate average quiz score
        $quizScores = StudentProgress::where('user_id', $studentId)
            ->where('progress_type', 'quiz')
            ->whereNotNull('score')
            ->pluck('score');

        $averageScore = $quizScores->isEmpty() ? 0 : round($quizScores->avg());

        return [
            'total' => $totalQuizzes,
            'completed' => $completedQuizzes,
            'percentage' => $percentage,
            'average_score' => $averageScore
        ];
    }

    /**
     * Helper method to get last activity - FIXED
     */
    private function getLastActivity($studentId)
    {
        $lastActivity = StudentProgress::where('user_id', $studentId)
            ->where('is_completed', true)
            ->orderBy('completed_at', 'desc')
            ->first();

        return $lastActivity ? $lastActivity->completed_at->toISOString() : null;
    }

    /**
     * Helper method to get detailed progress
     */
    private function getDetailedProgress($studentId)
    {
        // Placeholder - implement actual logic
        return [
            'materials' => [
                [
                    'id' => 1,
                    'title' => 'Introduction to Programming',
                    'completed' => true,
                    'completion_date' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
                    'time_spent' => 45 // minutes
                ],
                [
                    'id' => 2,
                    'title' => 'Variables and Data Types',
                    'completed' => true,
                    'completion_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
                    'time_spent' => 30
                ],
                [
                    'id' => 3,
                    'title' => 'Control Structures',
                    'completed' => false,
                    'completion_date' => null,
                    'time_spent' => 15
                ]
            ],
            'exercises' => [
                [
                    'id' => 1,
                    'title' => 'Basic Syntax Practice',
                    'completed' => true,
                    'completion_date' => date('Y-m-d H:i:s', strtotime('-12 days')),
                    'score' => 90
                ],
                [
                    'id' => 2,
                    'title' => 'Data Type Conversion',
                    'completed' => false,
                    'completion_date' => null,
                    'score' => null
                ]
            ],
            'quizzes' => [
                [
                    'id' => 1,
                    'title' => 'Programming Basics Quiz',
                    'completed' => true,
                    'completion_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
                    'score' => 85,
                    'attempts' => 1
                ],
                [
                    'id' => 2,
                    'title' => 'Variables and Operators Quiz',
                    'completed' => false,
                    'completion_date' => null,
                    'score' => null,
                    'attempts' => 0
                ]
            ]
        ];
    }

    // Helper method for material progress
    private function getStudentMaterialProgress($studentId)
    {
        try {
            $materials = Material::where('is_published', true)->get();

            return $materials->map(function ($material) use ($studentId) {
                $progress = StudentProgress::where('user_id', $studentId)
                    ->where('material_id', $material->id)
                    ->where('progress_type', 'material')
                    ->first();

                $videoProgress = StudentProgress::where('user_id', $studentId)
                    ->where('material_id', $material->id)
                    ->where('progress_type', 'material_video')
                    ->where('is_completed', true)
                    ->count();

                // Get total videos count safely
                $totalVideos = 0;
                try {
                    $totalVideos = $material->videos()->count();
                } catch (\Exception $e) {
                    Log::warning('Could not get videos count for material: ' . $material->id);
                }

                $progressPercentage = $totalVideos > 0 ?
                    round(($videoProgress / $totalVideos) * 100) : 0;

                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'difficulty_level' => $material->difficulty_level ?? 'beginner',
                    'is_completed' => $progress ? $progress->is_completed : false,
                    'completed_at' => $progress ? $progress->completed_at : null,
                    'progress_percentage' => $progressPercentage,
                    'total_videos' => $totalVideos,
                    'completed_videos' => $videoProgress,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error in getStudentMaterialProgress', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            return collect([]);
        }
    }

    // Helper method for exercise progress
    private function getStudentExerciseProgress($studentId)
    {
        try {
            $exercises = Exercise::where('is_published', true)->get();

            return $exercises->map(function ($exercise) use ($studentId) {
                $progress = StudentProgress::where('user_id', $studentId)
                    ->where('exercise_id', $exercise->id)
                    ->where('progress_type', 'exercise')
                    ->first();

                return [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'description' => $exercise->description,
                    'material_title' => $exercise->material ? $exercise->material->title : null,
                    'difficulty_level' => $exercise->difficulty_level ?? 'beginner',
                    'is_completed' => $progress ? $progress->is_completed : false,
                    'completed_at' => $progress ? $progress->completed_at : null,
                    'score' => $progress ? $progress->score : null,
                    'max_score' => $progress ? $progress->max_score : null,
                    'attempt_count' => $progress ? $progress->attempt_count : 0,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error in getStudentExerciseProgress', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            return collect([]);
        }
    }

    // Helper method for quiz progress
    private function getStudentQuizProgress($studentId)
    {
        try {
            $quizzes = Quiz::where('is_published', true)->get();

            return $quizzes->map(function ($quiz) use ($studentId) {
                $progress = StudentProgress::where('user_id', $studentId)
                    ->where('quiz_id', $quiz->id)
                    ->where('progress_type', 'quiz')
                    ->first();

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'material_title' => $quiz->material ? $quiz->material->title : null,
                    'passing_score' => $quiz->passing_score ?? 70,
                    'is_completed' => $progress ? $progress->is_completed : false,
                    'score' => $progress ? $progress->score : null,
                    'max_score' => $progress ? $progress->max_score : null,
                    'completed_at' => $progress ? $progress->completed_at : null,
                    'passed' => $progress ? ($progress->score >= ($quiz->passing_score ?? 70)) : false,
                    'attempt_count' => $progress ? $progress->attempt_count : 0,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error in getStudentQuizProgress', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            return collect([]);
        }
    }

    // For Teachers - Get overview statistics for dashboard
    public function getAllStudentsOverview(Request $request)
    {
        try {
            // Check if user exists and has teacher role
            $user = $request->user();
            if (!$user) {
                Log::warning('User not authenticated in getAllStudentsOverview');
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Check if user is a teacher
            if (!$user->isTeacher()) {
                Log::warning('Unauthorized access attempt to getAllStudentsOverview', [
                    'user_id' => $user->id,
                    'roles' => $user->getRoleSlugs(),
                    'expected_role' => 'teacher'
                ]);
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Get all students - adjust to use role relation
            $students = User::whereHas('roles', function($query) {
                $query->where('slug', 'student');
            })->get();

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
            Log::error('Error in getAllStudentsOverview', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    // Original methods for students to view their own progress
    public function index(Request $request)
    {
        try {
            // Check if user is a student
            $user = $request->user();
            if (!$user) {
                Log::warning('User not authenticated in student progress index');
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isStudent()) {
                Log::warning('Unauthorized access attempt to student progress index', [
                    'user_id' => $user->id,
                    'roles' => $user->getRoleSlugs(),
                    'expected_role' => 'student'
                ]);
                return response()->json(['message' => 'Unauthorized - Student access required'], 403);
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

        } catch (\Exception $e) {
            Log::error('Error in student progress index', [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    public function materials(Request $request)
    {
        try {
            // Check if user is a student
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isStudent()) {
                return response()->json(['message' => 'Unauthorized - Student access required'], 403);
            }

            $userId = $request->user()->id;
            return response()->json($this->getStudentMaterialProgress($userId));

        } catch (\Exception $e) {
            Log::error('Error in student materials progress', [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    public function exercises(Request $request)
    {
        try {
            // Check if user is a student
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isStudent()) {
                return response()->json(['message' => 'Unauthorized - Student access required'], 403);
            }

            $userId = $request->user()->id;
            return response()->json($this->getStudentExerciseProgress($userId));

        } catch (\Exception $e) {
            Log::error('Error in student exercises progress', [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    public function quizzes(Request $request)
    {
        try {
            // Check if user is a student
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isStudent()) {
                return response()->json(['message' => 'Unauthorized - Student access required'], 403);
            }

            $userId = $request->user()->id;
            return response()->json($this->getStudentQuizProgress($userId));

        } catch (\Exception $e) {
            Log::error('Error in student quizzes progress', [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    // Placeholder methods for other teacher routes that might be needed
    public function getStudentProgressSummary(Request $request, $studentId)
    {
        try {
            // Check if user is a teacher
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isTeacher()) {
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Implementation can be added as needed
            return response()->json(['message' => 'Not implemented yet'], 501);
        } catch (\Exception $e) {
            Log::error('Error in getStudentProgressSummary', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    // Implementasi metode getProgressAnalytics
    public function getProgressAnalytics(Request $request)
    {
        try {
            // Check if user is a teacher
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isTeacher()) {
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Get all students
            $students = User::whereHas('roles', function($query) {
                $query->where('slug', 'student');
            })->get();

            // Get all materials, exercises, and quizzes
            $materials = Material::where('is_published', true)->get();
            $exercises = Exercise::where('is_published', true)->get();
            $quizzes = Quiz::where('is_published', true)->get();

            // Calculate completion rates for each type
            $materialCompletionRate = [];
            $exerciseCompletionRate = [];
            $quizCompletionRate = [];
            $quizScores = [];

            foreach ($materials as $material) {
                $completedCount = StudentProgress::where('material_id', $material->id)
                    ->where('progress_type', 'material')
                    ->where('is_completed', true)
                    ->count();

                $materialCompletionRate[] = [
                    'id' => $material->id,
                    'title' => $material->title,
                    'completion_rate' => $students->count() > 0 ? ($completedCount / $students->count()) * 100 : 0,
                    'completed_count' => $completedCount,
                    'total_students' => $students->count()
                ];
            }

            foreach ($exercises as $exercise) {
                $completedCount = StudentProgress::where('exercise_id', $exercise->id)
                    ->where('progress_type', 'exercise')
                    ->where('is_completed', true)
                    ->count();

                $exerciseCompletionRate[] = [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'completion_rate' => $students->count() > 0 ? ($completedCount / $students->count()) * 100 : 0,
                    'completed_count' => $completedCount,
                    'total_students' => $students->count()
                ];
            }

            foreach ($quizzes as $quiz) {
                $completedCount = StudentProgress::where('quiz_id', $quiz->id)
                    ->where('progress_type', 'quiz')
                    ->where('is_completed', true)
                    ->count();

                $scores = StudentProgress::where('quiz_id', $quiz->id)
                    ->where('progress_type', 'quiz')
                    ->whereNotNull('score')
                    ->pluck('score');

                $averageScore = $scores->isEmpty() ? 0 : $scores->avg();

                $quizCompletionRate[] = [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'completion_rate' => $students->count() > 0 ? ($completedCount / $students->count()) * 100 : 0,
                    'completed_count' => $completedCount,
                    'total_students' => $students->count(),
                    'average_score' => round($averageScore, 1)
                ];

                $quizScores[] = [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'average_score' => round($averageScore, 1),
                    'passing_score' => $quiz->passing_score ?? 70,
                    'pass_rate' => $completedCount > 0 ?
                        (StudentProgress::where('quiz_id', $quiz->id)
                            ->where('progress_type', 'quiz')
                            ->where('score', '>=', $quiz->passing_score ?? 70)
                            ->count() / $completedCount) * 100 : 0
                ];
            }

            // Get weekly activity data for the last 8 weeks
            $weeklyActivity = [];
            for ($i = 7; $i >= 0; $i--) {
                $startDate = now()->subWeeks($i)->startOfWeek();
                $endDate = now()->subWeeks($i)->endOfWeek();

                $activityCount = StudentProgress::whereBetween('completed_at', [$startDate, $endDate])
                    ->where('is_completed', true)
                    ->count();

                $weeklyActivity[] = [
                    'week' => $startDate->format('M d') . ' - ' . $endDate->format('M d'),
                    'activity_count' => $activityCount
                ];
            }

            return response()->json([
                'material_completion' => $materialCompletionRate,
                'exercise_completion' => $exerciseCompletionRate,
                'quiz_completion' => $quizCompletionRate,
                'quiz_scores' => $quizScores,
                'weekly_activity' => $weeklyActivity,
                'total_students' => $students->count(),
                'active_students' => StudentProgress::where('completed_at', '>=', now()->subWeek())
                    ->distinct('user_id')
                    ->count('user_id')
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getProgressAnalytics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    public function getLeaderboard(Request $request)
    {
        try {
            // Check if user is a teacher
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isTeacher()) {
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Get all students with their progress
            $students = User::whereHas('roles', function($query) {
                $query->where('slug', 'student');
            })->get();

            $leaderboard = [];

            foreach ($students as $student) {
                // Calculate total progress
                $totalMaterials = Material::where('is_published', true)->count();
                $completedMaterials = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'material')
                    ->where('is_completed', true)
                    ->count();

                $totalExercises = Exercise::where('is_published', true)->count();
                $completedExercises = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'exercise')
                    ->where('is_completed', true)
                    ->count();

                $totalQuizzes = Quiz::where('is_published', true)->count();
                $completedQuizzes = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'quiz')
                    ->where('is_completed', true)
                    ->count();

                // Calculate average quiz score
                $quizScores = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'quiz')
                    ->whereNotNull('score')
                    ->pluck('score');

                $averageQuizScore = $quizScores->isEmpty() ? 0 : $quizScores->avg();

                // Calculate overall progress percentage
                $totalItems = $totalMaterials + $totalExercises + $totalQuizzes;
                $completedItems = $completedMaterials + $completedExercises + $completedQuizzes;
                $overallProgress = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;

                $leaderboard[] = [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'email' => $student->email,
                    ],
                    'overall_progress' => round($overallProgress),
                    'materials_completed' => $completedMaterials,
                    'exercises_completed' => $completedExercises,
                    'quizzes_completed' => $completedQuizzes,
                    'average_quiz_score' => round($averageQuizScore),
                    'last_activity' => StudentProgress::where('user_id', $student->id)
                        ->where('is_completed', true)
                        ->orderBy('completed_at', 'desc')
                        ->first()?->completed_at
                ];
            }

            // Sort by overall progress (descending)
            usort($leaderboard, function($a, $b) {
                return $b['overall_progress'] <=> $a['overall_progress'];
            });

            // Add rank
            foreach ($leaderboard as $index => $item) {
                $leaderboard[$index]['rank'] = $index + 1;
            }

            return response()->json($leaderboard);

        } catch (\Exception $e) {
            Log::error('Error in getLeaderboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    public function exportProgress(Request $request)
    {
        try {
            // Check if user is a teacher
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            if (!$user->isTeacher()) {
                return response()->json(['message' => 'Unauthorized - Teacher access required'], 403);
            }

            // Get all students with their progress
            $students = User::whereHas('roles', function($query) {
                $query->where('slug', 'student');
            })->get();

            $exportData = [];

            foreach ($students as $student) {
                // Calculate total progress
                $totalMaterials = Material::where('is_published', true)->count();
                $completedMaterials = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'material')
                    ->where('is_completed', true)
                    ->count();

                $totalExercises = Exercise::where('is_published', true)->count();
                $completedExercises = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'exercise')
                    ->where('is_completed', true)
                    ->count();

                $totalQuizzes = Quiz::where('is_published', true)->count();
                $completedQuizzes = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'quiz')
                    ->where('is_completed', true)
                    ->count();

                // Calculate average quiz score
                $quizScores = StudentProgress::where('user_id', $student->id)
                    ->where('progress_type', 'quiz')
                    ->whereNotNull('score')
                    ->pluck('score');

                $averageQuizScore = $quizScores->isEmpty() ? 0 : $quizScores->avg();

                // Calculate overall progress percentage
                $totalItems = $totalMaterials + $totalExercises + $totalQuizzes;
                $completedItems = $completedMaterials + $completedExercises + $completedQuizzes;
                $overallProgress = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;

                $exportData[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'student_email' => $student->email,
                    'overall_progress' => round($overallProgress),
                    'materials_completed' => $completedMaterials,
                    'materials_total' => $totalMaterials,
                    'materials_percentage' => $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 0,
                    'exercises_completed' => $completedExercises,
                    'exercises_total' => $totalExercises,
                    'exercises_percentage' => $totalExercises > 0 ? round(($completedExercises / $totalExercises) * 100) : 0,
                    'quizzes_completed' => $completedQuizzes,
                    'quizzes_total' => $totalQuizzes,
                    'quizzes_percentage' => $totalQuizzes > 0 ? round(($completedQuizzes / $totalQuizzes) * 100) : 0,
                    'average_quiz_score' => round($averageQuizScore),
                    'last_activity' => StudentProgress::where('user_id', $student->id)
                        ->where('is_completed', true)
                        ->orderBy('completed_at', 'desc')
                        ->first()?->completed_at
                ];
            }

            return response()->json([
                'export_date' => now()->format('Y-m-d H:i:s'),
                'total_students' => count($exportData),
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            Log::error('Error in exportProgress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

// Helper method for material progress - ENHANCED
// private function getStudentMaterialProgress($studentId)
// {
//     try {
//         $materials = Material::where('is_published', true)->get();

//         return $materials->map(function ($material) use ($studentId) {
//             $progress = StudentProgress::where('user_id', $studentId)
//                 ->where('material_id', $material->id)
//                 ->where('progress_type', 'material')
//                 ->first();

//             // Count completed videos for this material
//             $completedVideos = StudentProgress::where('user_id', $studentId)
//                 ->where('material_id', $material->id)
//                 ->where('progress_type', 'material_video')
//                 ->where('is_completed', true)
//                 ->count();

//             // Get total videos count
//             $totalVideos = 0;
//             try {
//                 // Try to get videos count from material_videos table or videos relation
//                 $totalVideos = \DB::table('material_videos')
//                     ->where('material_id', $material->id)
//                     ->count();

//                 // If no videos table, use a default or try videos relation
//                 if ($totalVideos == 0 && method_exists($material, 'videos')) {
//                     $totalVideos = $material->videos()->count();
//                 }
//             } catch (\Exception $e) {
//                 Log::warning('Could not get videos count for material: ' . $material->id);
//                 $totalVideos = 1; // Default to 1 to avoid division by zero
//             }

//             $progressPercentage = $totalVideos > 0 ?
//                 round(($completedVideos / $totalVideos) * 100) : 0;

//             return [
//                 'id' => $material->id,
//                 'title' => $material->title,
//                 'description' => $material->description,
//                 'difficulty_level' => $material->difficulty_level ?? 'beginner',
//                 'is_completed' => $progress ? $progress->is_completed : false,
//                 'completed_at' => $progress ? $progress->completed_at : null,
//                 'progress_percentage' => $progressPercentage,
//                 'total_videos' => $totalVideos,
//                 'completed_videos' => $completedVideos,
//             ];
//         });
//     } catch (\Exception $e) {
//         Log::error('Error in getStudentMaterialProgress', [
//             'student_id' => $studentId,
//             'error' => $e->getMessage()
//         ]);
//         return collect([]);
//     }
// }

// // Helper method for exercise progress - ENHANCED
// private function getStudentExerciseProgress($studentId)
// {
//     try {
//         $exercises = Exercise::where('is_published', true)
//             ->with('material')
//             ->get();

//         return $exercises->map(function ($exercise) use ($studentId) {
//             $progress = StudentProgress::where('user_id', $studentId)
//                 ->where('exercise_id', $exercise->id)
//                 ->where('progress_type', 'exercise')
//                 ->orderBy('created_at', 'desc')
//                 ->first();

//             // Get best score for this exercise
//             $bestScore = StudentProgress::where('user_id', $studentId)
//                 ->where('exercise_id', $exercise->id)
//                 ->where('progress_type', 'exercise')
//                 ->max('score');

//             // Count attempts
//             $attemptCount = StudentProgress::where('user_id', $studentId)
//                 ->where('exercise_id', $exercise->id)
//                 ->where('progress_type', 'exercise')
//                 ->count();

//             return [
//                 'id' => $exercise->id,
//                 'title' => $exercise->title,
//                 'description' => $exercise->description,
//                 'material_title' => $exercise->material ? $exercise->material->title : null,
//                 'difficulty_level' => $exercise->difficulty_level ?? 'beginner',
//                 'is_completed' => $progress ? $progress->is_completed : false,
//                 'completed_at' => $progress ? $progress->completed_at : null,
//                 'score' => $bestScore, // Use best score instead of last score
//                 'max_score' => $exercise->total_points ?? 100,
//                 'attempt_count' => $attemptCount,
//             ];
//         });
//     } catch (\Exception $e) {
//         Log::error('Error in getStudentExerciseProgress', [
//             'student_id' => $studentId,
//             'error' => $e->getMessage()
//         ]);
//         return collect([]);
//     }
// }

// // Helper method for quiz progress - ENHANCED
// private function getStudentQuizProgress($studentId)
// {
//     try {
//         $quizzes = Quiz::where('is_published', true)
//             ->with('material')
//             ->get();

//         return $quizzes->map(function ($quiz) use ($studentId) {
//             $progress = StudentProgress::where('user_id', $studentId)
//                 ->where('quiz_id', $quiz->id)
//                 ->where('progress_type', 'quiz')
//                 ->orderBy('created_at', 'desc')
//                 ->first();

//             // Get best score for this quiz
//             $bestScore = StudentProgress::where('user_id', $studentId)
//                 ->where('quiz_id', $quiz->id)
//                 ->where('progress_type', 'quiz')
//                 ->max('score');

//             // Count attempts
//             $attemptCount = StudentProgress::where('user_id', $studentId)
//                 ->where('quiz_id', $quiz->id)
//                 ->where('progress_type', 'quiz')
//                 ->count();

//             $passingScore = $quiz->passing_score ?? 70;
//             $maxScore = $quiz->total_points ?? 100;

//             return [
//                 'id' => $quiz->id,
//                 'title' => $quiz->title,
//                 'description' => $quiz->description,
//                 'material_title' => $quiz->material ? $quiz->material->title : null,
//                 'passing_score' => $passingScore,
//                 'is_completed' => $progress ? $progress->is_completed : false,
//                 'score' => $bestScore, // Use best score instead of last score
//                 'max_score' => $maxScore,
//                 'completed_at' => $progress ? $progress->completed_at : null,
//                 'passed' => $bestScore ? ($bestScore >= $passingScore) : false,
//                 'attempt_count' => $attemptCount,
//             ];
//         });
//     } catch (\Exception $e) {
//         Log::error('Error in getStudentQuizProgress', [
//             'student_id' => $studentId,
//             'error' => $e->getMessage()
//         ]);
//         return collect([]);
//     }
// }
}
