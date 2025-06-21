<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseQuestion;
use App\Models\ExerciseOption;
use App\Models\StudentProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExerciseController extends Controller
{
    /**
     * Display a listing of exercises
     */
    public function index(Request $request)
    {
        $query = Exercise::with(['creator', 'material', 'questions.materialVideo']);

        // Filter by published status for students
        if ($request->user()->isStudent()) {
            $query->where('is_published', true);
        }

        // Filter by creator for teachers
        if ($request->user()->isTeacher() && $request->has('my_exercises')) {
            $query->where('created_by', $request->user()->id);
        }

        // Filter by material
        if ($request->has('material_id')) {
            $query->where('material_id', $request->material_id);
        }

        // Filter by difficulty level
        if ($request->has('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $exercises = $query->latest()->paginate(10);

        // If user is a student, add progress information
        if ($request->user()->isStudent()) {
            $exercises->each(function ($exercise) use ($request) {
                $progress = StudentProgress::where('user_id', $request->user()->id)
                    ->where('exercise_id', $exercise->id)
                    ->first();

                $exercise->is_completed = $progress ? $progress->is_completed : false;
                $exercise->score = $progress ? $progress->score : null;
                $exercise->attempt_count = $progress ? $progress->attempt_count : 0;
            });
        }

        return response()->json($exercises);
    }

    /**
     * Store a newly created exercise
     */
    public function store(Request $request)
    {
        // Check if user is a teacher
        if (!$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'material_id' => 'required|exists:materials,id',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'is_published' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.material_video_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($request) {
                    // Cek apakah video exists
                    $video = \App\Models\MaterialVideo::find($value);
                    if (!$video) {
                        $fail("Video dengan ID {$value} tidak ditemukan");
                        return;
                    }

                    // Cek apakah video belongs to material
                    if ($video->material_id != $request->material_id) {
                        $fail("Video ID {$value} tidak termasuk dalam material ID {$request->material_id}");
                        return;
                    }

                    // Cek authorization
                    $material = $video->material;
                    $user = $request->user();

                    if (!$material->is_published &&
                        $user->id !== $material->created_by &&
                        !$user->isTeacher()) {
                        $fail("Anda tidak memiliki akses ke video ID {$value}");
                        return;
                    }
                },
            ],
            'questions.*.question' => 'required|string',
            'questions.*.points' => 'integer|min:1|max:100',
            'questions.*.options' => 'required|array|min:2|max:6',
            'questions.*.options.*' => 'required|string',
            'questions.*.correct_answer' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create exercise
            $exercise = Exercise::create([
                'title' => $request->title,
                'description' => $request->description,
                'material_id' => $request->material_id,
                'created_by' => $request->user()->id,
                'difficulty_level' => $request->difficulty_level,
                'is_published' => $request->is_published ?? false,
            ]);

            // Create questions and options
            foreach ($request->questions as $index => $questionData) {
                // Validate correct_answer index
                if ($questionData['correct_answer'] >= count($questionData['options'])) {
                    throw new \Exception("Invalid correct_answer index for question " . ($index + 1));
                }

                $question = ExerciseQuestion::create([
                    'exercise_id' => $exercise->id,
                    'material_video_id' => $questionData['material_video_id'],
                    'question' => $questionData['question'],
                    'points' => $questionData['points'] ?? 10,
                    'order' => $index + 1,
                ]);

                // Create options
                foreach ($questionData['options'] as $optionIndex => $optionText) {
                    ExerciseOption::create([
                        'exercise_question_id' => $question->id,
                        'option_text' => $optionText,
                        'is_correct' => $optionIndex == $questionData['correct_answer'],
                        'order' => $optionIndex + 1,
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $exercise->load(['questions.options', 'questions.materialVideo', 'material', 'creator']);

            return response()->json($exercise, 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Exercise creation error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Exercise creation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified exercise
     */
    public function show(Request $request, $id)
    {
        $exercise = Exercise::with([
            'creator',
            'material',
            'questions.options',
            'questions.materialVideo'
        ])->findOrFail($id);

        // Check if exercise is published or user is the creator
        if (!$exercise->is_published && $request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If user is a student, add progress information and hide correct answers
        if ($request->user()->isStudent()) {
            $progress = StudentProgress::where('user_id', $request->user()->id)
                ->where('exercise_id', $exercise->id)
                ->first();

            $exercise->is_completed = $progress ? $progress->is_completed : false;
            $exercise->score = $progress ? $progress->score : null;
            $exercise->attempt_count = $progress ? $progress->attempt_count : 0;

            // Hide correct answers from students
            $exercise->questions->each(function ($question) {
                $question->options->each(function ($option) {
                    unset($option->is_correct);
                });
            });
        }

        return response()->json($exercise);
    }

    /**
     * Update the specified exercise
     */
    public function update(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'material_id' => 'required|exists:materials,id',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'is_published' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.material_video_id' => 'required|exists:material_videos,id',
            'questions.*.question' => 'required|string',
            'questions.*.points' => 'integer|min:1|max:100',
            'questions.*.options' => 'required|array|min:2|max:6',
            'questions.*.options.*' => 'required|string',
            'questions.*.correct_answer' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Update exercise
            $exercise->update([
                'title' => $request->title,
                'description' => $request->description,
                'material_id' => $request->material_id,
                'difficulty_level' => $request->difficulty_level,
                'is_published' => $request->is_published ?? $exercise->is_published,
            ]);

            // Delete existing questions and options (cascade will handle options)
            $exercise->questions()->delete();

            // Create new questions and options
            foreach ($request->questions as $index => $questionData) {
                $question = ExerciseQuestion::create([
                    'exercise_id' => $exercise->id,
                    'material_video_id' => $questionData['material_video_id'],
                    'question' => $questionData['question'],
                    'points' => $questionData['points'] ?? 10,
                    'order' => $index + 1,
                ]);

                // Create options
                foreach ($questionData['options'] as $optionIndex => $optionText) {
                    ExerciseOption::create([
                        'exercise_question_id' => $question->id,
                        'option_text' => $optionText,
                        'is_correct' => $optionIndex == $questionData['correct_answer'],
                        'order' => $optionIndex + 1,
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $exercise->load(['questions.options', 'questions.materialVideo', 'material', 'creator']);

            return response()->json($exercise);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Exercise update error: ' . $e->getMessage());
            return response()->json(['error' => 'Exercise update failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified exercise
     */
    public function destroy(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exercise->delete(); // Cascade will handle questions and options

        return response()->json(['message' => 'Exercise deleted successfully']);
    }

    /**
     * Submit single question answer with immediate feedback
     */
    public function submitSingleAnswer(Request $request, $exerciseId, $questionId)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exercise = Exercise::with('questions')->findOrFail($exerciseId);
        $question = ExerciseQuestion::with(['options', 'materialVideo'])->findOrFail($questionId);

        // Validate question belongs to exercise
        if ($question->exercise_id != $exerciseId) {
            return response()->json(['message' => 'Question not found in this exercise'], 404);
        }

        // Check if exercise is published
        if (!$exercise->is_published) {
            return response()->json(['message' => 'Exercise not available'], 403);
        }

        $validator = Validator::make($request->all(), [
            'selected_option_id' => 'required|integer|exists:exercise_options,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get or create progress
            $progress = StudentProgress::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'exercise_id' => $exerciseId,
                    'progress_type' => 'exercise',
                ],
                [
                    'score' => 0,
                    'max_score' => $exercise->total_points,
                    'attempt_count' => 1,
                    'started_at' => now(),
                    'is_completed' => false,
                ]
            );

            $selectedOption = $question->options()->findOrFail($request->selected_option_id);
            $correctOption = $question->options()->where('is_correct', true)->first();
            $isCorrect = $selectedOption->is_correct;
            $pointsEarned = $isCorrect ? $question->points : 0;

            // Add answer to progress using helper method
            $progress->addAnswer($questionId, $request->selected_option_id, $isCorrect, $pointsEarned);

            // Recalculate total score
            $answers = $progress->answers_detail ?? [];
            $totalScore = collect($answers)->sum('points_earned');
            $progress->score = $totalScore;

            // Check if all questions answered
            $totalQuestions = $exercise->questions()->count();
            $answeredQuestions = count($answers);
            $isLastQuestion = $answeredQuestions >= $totalQuestions;

            if ($isLastQuestion) {
                $progress->is_completed = true;
                $progress->completed_at = now();
            }

            $progress->save();

            DB::commit();

            // Prepare response
            $response = [
                'question_id' => $questionId,
                'selected_option' => [
                    'id' => $selectedOption->id,
                    'text' => $selectedOption->option_text,
                    'is_correct' => $selectedOption->is_correct,
                ],
                'correct_option' => [
                    'id' => $correctOption->id,
                    'text' => $correctOption->option_text,
                ],
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
                'max_points' => $question->points,
                'explanation' => $this->getExplanation($question, $isCorrect),
                'is_last_question' => $isLastQuestion,
                'current_progress' => [
                    'answered_questions' => $answeredQuestions,
                    'total_questions' => $totalQuestions,
                    'current_score' => $totalScore,
                    'max_score' => $exercise->total_points,
                ]
            ];

            if ($isLastQuestion) {
                $response['final_results'] = [
                    'total_score' => $totalScore,
                    'max_score' => $exercise->total_points,
                    'percentage' => round(($totalScore / $exercise->total_points) * 100, 2),
                    'correct_answers' => $progress->getCorrectAnswersCount(),
                    'total_questions' => $totalQuestions,
                    'message' => $this->getFinalMessage($totalScore, $exercise->total_points),
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Exercise single answer submission error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to submit answer'], 500);
        }
    }

    /**
     * Submit all answers at once (traditional method)
     */
    public function submitAnswers(Request $request, $id)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exercise = Exercise::with('questions.options')->findOrFail($id);

        // Check if exercise is published
        if (!$exercise->is_published) {
            return response()->json(['message' => 'Exercise not available'], 403);
        }

        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*' => 'required|integer|exists:exercise_options,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $totalScore = 0;
            $maxScore = $exercise->total_points;
            $correctAnswers = 0;
            $totalQuestions = $exercise->total_questions;

            // Prepare answers detail for JSON storage
            $answersDetail = [];

            // Calculate score
            foreach ($request->answers as $questionId => $selectedOptionId) {
                $question = $exercise->questions->find($questionId);
                if ($question) {
                    $selectedOption = $question->options->find($selectedOptionId);
                    if ($selectedOption) {
                        $isCorrect = $selectedOption->is_correct;
                        $pointsEarned = $isCorrect ? $question->points : 0;

                        $totalScore += $pointsEarned;
                        if ($isCorrect) {
                            $correctAnswers++;
                        }

                        // Store answer detail
                        $answersDetail[$questionId] = [
                            'selected_option_id' => $selectedOptionId,
                            'is_correct' => $isCorrect,
                            'points_earned' => $pointsEarned,
                            'answered_at' => now()->toISOString(),
                        ];
                    }
                }
            }

            // Update or create progress
            $progress = StudentProgress::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'exercise_id' => $id,
                    'progress_type' => 'exercise',
                ],
                [
                    'score' => $totalScore,
                    'max_score' => $maxScore,
                    'answers_detail' => $answersDetail,
                    'is_completed' => true,
                    'completed_at' => now(),
                    'attempt_count' => DB::raw('COALESCE(attempt_count, 0) + 1'),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Exercise completed successfully',
                'score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => round(($totalScore / $maxScore) * 100, 2),
                'correct_answers' => $correctAnswers,
                'total_questions' => $totalQuestions,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Exercise submission error: ' . $e->getMessage());
            return response()->json(['error' => 'Submission failed'], 500);
        }
    }

    /**
     * Get student's current progress in exercise
     */
    public function getProgress(Request $request, $exerciseId)
    {
        $exercise = Exercise::with('questions.options')->findOrFail($exerciseId);

        $progress = StudentProgress::where('user_id', $request->user()->id)
            ->where('exercise_id', $exerciseId)
            ->first();

        $answersDetail = $progress ? ($progress->answers_detail ?? []) : [];

        $progressData = [
            'exercise_id' => $exerciseId,
            'total_questions' => $exercise->questions()->count(),
            'answered_questions' => count($answersDetail),
            'current_score' => $progress ? $progress->score : 0,
            'max_possible_score' => $exercise->total_points,
            'is_completed' => $progress ? $progress->is_completed : false,
            'attempt_count' => $progress ? $progress->attempt_count : 0,
            'questions_progress' => []
        ];

        foreach ($exercise->questions as $question) {
            $answer = $answersDetail[$question->id] ?? null;

            $progressData['questions_progress'][] = [
                'question_id' => $question->id,
                'question_order' => $question->order,
                'is_answered' => $answer ? true : false,
                'is_correct' => $answer ? $answer['is_correct'] : null,
                'points_earned' => $answer ? $answer['points_earned'] : 0,
                'max_points' => $question->points,
            ];
        }

        return response()->json($progressData);
    }

    /**
     * Reset exercise progress (allow retake)
     */
    public function resetProgress(Request $request, $id)
    {
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exercise = Exercise::findOrFail($id);

        if (!$exercise->is_published) {
            return response()->json(['message' => 'Exercise not available'], 403);
        }

        $progress = StudentProgress::where('user_id', $request->user()->id)
            ->where('exercise_id', $id)
            ->first();

        if ($progress) {
            $progress->update([
                'score' => 0,
                'answers_detail' => null,
                'is_completed' => false,
                'completed_at' => null,
                'started_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Exercise progress reset successfully']);
    }

    /**
     * Stream video for exercise question (via MaterialVideo)
     */
    public function stream(Request $request, $exerciseId, $questionId)
    {
        $exercise = Exercise::findOrFail($exerciseId);
        $question = ExerciseQuestion::with('materialVideo')->findOrFail($questionId);

        // Validate question belongs to exercise
        if ($question->exercise_id != $exerciseId) {
            return response()->json(['message' => 'Question not found in this exercise'], 404);
        }

        // Check if exercise is published or user is the creator
        if (!$exercise->is_published && $request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get MaterialVideo
        $materialVideo = $question->materialVideo;
        if (!$materialVideo || !$materialVideo->video_path) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        if (!Storage::disk('public')->exists($materialVideo->video_path)) {
            return response()->json(['error' => 'Video file not found'], 404);
        }

        $file = Storage::disk('public')->path($materialVideo->video_path);
        $mimeType = $materialVideo->video_type ?: 'video/mp4';
        $size = Storage::disk('public')->size($materialVideo->video_path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="' . ($materialVideo->video_filename ?: 'video.mp4') . '"',
        ];

        // Handle range requests for video streaming
        if ($request->header('Range')) {
            return $this->handleRangeRequest($request, $file, $size, $mimeType);
        }

        return response()->file($file, $headers);
    }

    /**
     * Handle range requests for video streaming
     */
    protected function handleRangeRequest(Request $request, $file, $size, $mimeType)
    {
        $range = $request->header('Range');
        $ranges = explode('=', $range);
        $ranges = explode('-', $ranges[1]);

        $start = intval($ranges[0]);
        $end = isset($ranges[1]) && !empty($ranges[1]) ? intval($ranges[1]) : $size - 1;

        // Validate range
        if ($start >= $size || $end >= $size) {
            return response('Requested range not satisfiable', 416, [
                'Content-Range' => "bytes */$size"
            ]);
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $length,
            'Accept-Ranges' => 'bytes',
            'Content-Range' => "bytes $start-$end/$size",
        ];

        return response()->stream(function () use ($file, $start, $end) {
            $handle = fopen($file, 'rb');
            fseek($handle, $start);
            $buffer = 1024 * 8; // 8KB buffer
            $currentPosition = $start;

            while (!feof($handle) && $currentPosition <= $end) {
                $bytesToRead = min($buffer, $end - $currentPosition + 1);
                echo fread($handle, $bytesToRead);
                flush();
                $currentPosition += $bytesToRead;
            }

            fclose($handle);
        }, 206, $headers);
    }

    /**
     * Get explanation for answer
     */
    private function getExplanation($question, $isCorrect)
    {
        if ($isCorrect) {
            return "Benar! Anda berhasil mengenali bahasa isyarat dengan tepat.";
        } else {
            $correctOption = $question->options()->where('is_correct', true)->first();
            return "Kurang tepat. Jawaban yang benar adalah: " . $correctOption->option_text . ". Coba perhatikan gerakan tangan dan posisi jari dalam video.";
        }
    }

    /**
     * Get final message based on score
     */
    private function getFinalMessage($score, $maxScore)
    {
        $percentage = ($score / $maxScore) * 100;

        if ($percentage >= 90) {
            return "Excellent! Anda sangat menguasai materi ini.";
        } elseif ($percentage >= 80) {
            return "Great! Pemahaman Anda sudah sangat baik.";
        } elseif ($percentage >= 70) {
            return "Good! Anda cukup memahami materi ini.";
        } elseif ($percentage >= 60) {
            return "Fair. Masih perlu latihan lebih banyak.";
        } else {
            return "Perlu belajar lebih giat lagi. Jangan menyerah!";
        }
    }
}
