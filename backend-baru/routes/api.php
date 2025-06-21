<?php

use App\Http\Controllers\API\AchievementController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ExerciseController;
use App\Http\Controllers\API\MaterialController;
use App\Http\Controllers\API\MaterialVideoController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PasswordResetController;
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

// Email verification routes (public) - TANPA CSRF PROTECTION
Route::post('/email/verify', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
    ->name('verification.resend');
Route::post('/email/check-status', [AuthController::class, 'checkVerificationStatus'])
    ->name('verification.check');

// Handle OPTIONS requests for CORS
Route::options('/email/verify', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
    ]);
});

Route::options('/email/resend', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
    ]);
});

// Password reset routes (public) - TANPA CSRF PROTECTION
Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLinkEmail'])
    ->name('password.email');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])
    ->name('password.reset');
Route::post('/password/validate-token', [PasswordResetController::class, 'validateToken'])
    ->name('password.validate');

// Handle OPTIONS requests for CORS
Route::options('/password/forgot', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
    ]);
});

Route::options('/password/reset', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
    ]);
});

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
    Route::put('/materials/{material}/videos/{video}', [MaterialVideoController::class, 'update']);
    Route::delete('/materials/{material}/videos/{video}', [MaterialVideoController::class, 'destroy']);
    Route::post('/materials/{material}/videos/{video}/complete', [MaterialVideoController::class, 'markAsCompleted']);

    // Tambahkan route streaming untuk material videos
    Route::get('/materials/{materialId}/videos/{videoId}/stream', [MaterialVideoController::class, 'stream'])
        ->name('api.materials.videos.stream');

    Route::apiResource('exercises', ExerciseController::class);

    // Exercise Immediate Feedback
    Route::post('/exercises/{exercise}/questions/{question}/answer', [ExerciseController::class, 'submitSingleAnswer']);

    // Exercise Progress
    Route::get('/exercises/{exercise}/progress', [ExerciseController::class, 'getProgress']);
    Route::post('/exercises/{exercise}/reset', [ExerciseController::class, 'resetProgress']);

    // Exercise Video Streaming
    Route::get('/exercises/{exercise}/questions/{question}/stream', [ExerciseController::class, 'stream'])
        ->name('api.exercises.questions.stream');

    // Traditional submit all answers
    Route::post('/exercises/{exercise}/submit', [ExerciseController::class, 'submitAnswers']);

    // Quiz routes - EXISTING TEACHER ROUTES (unchanged)
    Route::apiResource('quizzes', QuizController::class);

    // Quiz Questions Management (Teacher functionality)
    Route::get('/quizzes/{quiz}/questions', [QuizController::class, 'getQuestions']);
    Route::post('/quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion']);
    Route::put('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion']);
    Route::delete('/quizzes/{quiz}/questions/{question}', [QuizController::class, 'deleteQuestion']);

    // Quiz video streaming for teachers
    Route::get('/quizzes/{id}/questions/{questionId}/stream', [QuizController::class, 'streamQuestionVideo'])
        ->name('api.quiz.questions.stream');

    // NEW STUDENT-SPECIFIC QUIZ ROUTES
    Route::post('/quizzes/{id}/start', [QuizController::class, 'startAttempt']);
    Route::get('/quizzes/{id}/status', [QuizController::class, 'getQuizStatus']);
    Route::post('/quizzes/{id}/reset', [QuizController::class, 'resetProgress']);

    // Alternative submit endpoint (same as legacy but clearer naming)
    Route::post('/quizzes/{id}/submit', [QuizController::class, 'submitAnswers']); // ← Frontend uses this
    Route::post('/quizzes/{id}/submit-answers', [QuizController::class, 'submitAnswers']); // Alternative

    // Additional quiz routes for future features
    Route::get('/quizzes/{quizId}/attempts', [QuizController::class, 'getUserAttempts']);
    Route::get('/quizzes/{quizId}/attempts/{attemptId}/results', [QuizController::class, 'getAttemptResults']);

    // Teacher routes - untuk monitoring semua student
    // PERBAIKAN: Gunakan prefix dengan garis miring di depan
    Route::prefix('/teacher')->group(function () {
        // Overview semua student progress
        Route::get('/students/progress/overview', [StudentProgressController::class, 'getAllStudentsOverview']);

        // Daftar semua student dengan basic progress info - ini yang dipanggil frontend
        Route::get('/students/progress', [StudentProgressController::class, 'getAllStudentsProgress']);

        // Detail progress individual student
        Route::get('/students/{studentId}/progress', [StudentProgressController::class, 'getStudentDetailProgress']);
        Route::get('/students/{studentId}/progress/summary', [StudentProgressController::class, 'getStudentProgressSummary']);

        // Progress berdasarkan kategori (semua student)
        Route::get('/students/progress/materials', [StudentProgressController::class, 'getAllStudentsProgressByMaterials']);
        Route::get('/students/progress/exercises', [StudentProgressController::class, 'getAllStudentsProgressByExercises']);
        Route::get('/students/progress/quizzes', [StudentProgressController::class, 'getAllStudentsProgressByQuizzes']);

        // Progress berdasarkan material/exercise/quiz tertentu
        Route::get('/materials/{materialId}/students/progress', [StudentProgressController::class, 'getStudentsProgressByMaterial']);
        Route::get('/exercises/{exerciseId}/students/progress', [StudentProgressController::class, 'getStudentsProgressByExercise']);
        Route::get('/quizzes/{quizId}/students/progress', [StudentProgressController::class, 'getStudentsProgressByQuiz']);

        // Analytics dan reports
        Route::get('/students/progress/analytics', [StudentProgressController::class, 'getProgressAnalytics']);
        Route::get('/students/progress/leaderboard', [StudentProgressController::class, 'getLeaderboard']);
        Route::get('/students/progress/export', [StudentProgressController::class, 'exportProgress']);
    });
    // routes/api.php
    // Student routes
    Route::prefix('student')->group(function () {
        Route::get('/progress', [StudentProgressController::class, 'index']);
        Route::get('/progress/materials', [StudentProgressController::class, 'materials']);
        Route::get('/progress/exercises', [StudentProgressController::class, 'exercises']);
        Route::get('/progress/quizzes', [StudentProgressController::class, 'quizzes']);

        // NEW: Student's own progress endpoint
        Route::get('/my-progress', [StudentProgressController::class, 'getMyProgress']);
    });

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
