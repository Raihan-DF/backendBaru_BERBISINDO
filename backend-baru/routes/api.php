<?php

use App\Http\Controllers\API\AchievementController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ExerciseController;
use App\Http\Controllers\API\MaterialController;
use App\Http\Controllers\API\MaterialVideoController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\QuizController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\SettingController;
use App\Http\Controllers\API\StudentProgressController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Route::post('/token', [AuthController::class, 'token']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/user/profile', [AuthController::class, 'updateProfile']);

    // Materials
    Route::apiResource('materials', MaterialController::class);

    // Material Videos
    Route::get('/materials/{material}/videos', [MaterialVideoController::class, 'index']);
    Route::post('/materials/{material}/videos', [MaterialVideoController::class, 'store']);
    Route::get('/materials/{material}/videos/{video}', [MaterialVideoController::class, 'show']);
    Route::post('/materials/{material}/videos/{video}', [MaterialVideoController::class, 'update']);
    Route::delete('/materials/{material}/videos/{video}', [MaterialVideoController::class, 'destroy']);
    Route::post('/materials/{material}/videos/{video}/complete', [MaterialVideoController::class, 'markAsCompleted']);

    // Tambahkan route streaming untuk material videos
    Route::get('/materials/{materialId}/videos/{videoId}/stream', [MaterialVideoController::class, 'stream'])
        ->name('api.materials.videos.stream');

    // Exercises
    Route::apiResource('exercises', ExerciseController::class);
    Route::post('/exercises/{exercise}/complete', [ExerciseController::class, 'markAsCompleted']);

    // Tambahkan route streaming untuk exercise videos
    Route::get('/exercises/{id}/stream', [ExerciseController::class, 'stream'])
        ->name('api.exercises.stream');

    // Quizzes
    Route::apiResource('quizzes', QuizController::class);
    Route::get('/quizzes/{quiz}/questions', [QuizController::class, 'getQuestions']);
    Route::post('/quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion']);
    Route::put('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion']);
    Route::delete('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'deleteQuestion']);
    Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submitQuiz']);

    // Tambahkan route streaming untuk quiz question videos
    Route::get('/quizzes/{id}/questions/{questionId}/stream', [QuizController::class, 'streamQuestionVideo'])
        ->name('api.quiz.questions.stream');

    // Student Progress
    Route::get('/progress', [StudentProgressController::class, 'index']);
    Route::get('/progress/materials', [StudentProgressController::class, 'materials']);
    Route::get('/progress/exercises', [StudentProgressController::class, 'exercises']);
    Route::get('/progress/quizzes', [StudentProgressController::class, 'quizzes']);

    // Achievements
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/achievements/user', [AchievementController::class, 'userAchievements']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read/{notification}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    // Search
    Route::get('/search', [SearchController::class, 'index']);

    // Teacher-only routes
    Route::middleware('role:teacher')->group(function () {
        // Tambahkan route khusus untuk teacher jika diperlukan
    });

    // Student-only routes
    Route::middleware('role:student')->group(function () {
        // Tambahkan route khusus untuk student jika diperlukan
    });
});
