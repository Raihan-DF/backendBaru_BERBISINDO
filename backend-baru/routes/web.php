<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

// Test email route untuk Laravel 11
Route::get('/test-email', function () {
    try {
        // Test basic email sending
        Mail::raw('Test email dari Laravel 11 dengan Gmail', function ($message) {
            $message->to('raihanloq7@gmail.com') // Ganti dengan email asli
                   ->subject('Test Email Laravel 11');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Email berhasil dikirim! Cek log atau inbox.',
            'mail_config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from' => config('mail.from'),
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
            'mail_config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from' => config('mail.from'),
            ]
        ], 500);
    }
});

// Test verification email TANPA QUEUE
Route::get('/test-verification-sync/{email}', function ($email) {
    try {
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        Log::info('Sending SYNC verification email to: ' . $email);

        // Kirim email langsung tanpa queue
        $user->notify(new \App\Notifications\CustomVerifyEmail);

        Log::info('SYNC Verification email sent successfully');

        return response()->json([
            'status' => 'success',
            'message' => 'SYNC Verification email sent to: ' . $email,
            'user' => $user->only(['id', 'name', 'email', 'email_verified_at'])
        ]);
    } catch (Exception $e) {
        Log::error('SYNC Verification email error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test verification email
Route::get('/test-verification/{email}', function ($email) {
    try {
        // Cek apakah user ada
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            // Tampilkan semua user untuk debug
            $allUsers = \App\Models\User::select('id', 'email')->get();
            return response()->json([
                'error' => 'User not found',
                'searched_email' => $email,
                'existing_users' => $allUsers,
                'suggestion' => 'Please register first or check email spelling'
            ], 404);
        }

        Log::info('Sending verification email to: ' . $email);
        $user->sendEmailVerificationNotification();
        Log::info('Verification email sent successfully');

        return response()->json([
            'status' => 'success',
            'message' => 'Verification email sent to: ' . $email,
            'user' => $user->only(['id', 'name', 'email', 'email_verified_at'])
        ]);
    } catch (Exception $e) {
        Log::error('Verification email error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// DEBUG: Test route untuk memastikan routing bekerja
Route::get('/test-cors', function () {
    return response()->json([
        'message' => 'CORS test route working',
        'timestamp' => now(),
    ], 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range',
    ]);
});

// SOLUSI ALTERNATIF: Dedicated video route yang PASTI di-hit
Route::get('/video/{materialId}/{filename}', function (Request $request, $materialId, $filename) {
    Log::info('=== VIDEO ROUTE HIT ===', [
        'materialId' => $materialId,
        'filename' => $filename,
        'full_url' => $request->fullUrl(),
        'method' => $request->method()
    ]);

    // Construct path
    $path = "material_videos/{$materialId}/{$filename}";

    // Check if file exists in public disk
    if (!Storage::disk('public')->exists($path)) {
        Log::error('Video file not found', ['path' => $path]);
        abort(404, 'Video file not found');
    }

    $file = Storage::disk('public')->path($path);

    // Extension-based mime type detection
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $mimeTypes = [
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
    ];

    $mimeType = $mimeTypes[$extension] ?? 'video/mp4';
    $size = filesize($file);

    // FORCE CORS HEADERS
    $headers = [
        'Content-Type' => $mimeType,
        'Content-Length' => $size,
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'public, max-age=3600',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range',
        'Access-Control-Expose-Headers' => 'Content-Range, Accept-Ranges, Content-Length',
        'X-Video-Route' => 'dedicated-video-route', // Marker
        'X-Material-ID' => $materialId,
        'X-Filename' => $filename,
    ];

    Log::info('=== SERVING VIDEO WITH CORS ===', [
        'file' => $file,
        'mime' => $mimeType,
        'size' => $size,
        'material_id' => $materialId,
        'filename' => $filename
    ]);

    // Handle OPTIONS request
    if ($request->method() === 'OPTIONS') {
        Log::info('OPTIONS request for video');
        return response('', 200, $headers);
    }

    // Handle range requests for video streaming
    if ($request->header('Range')) {
        $range = $request->header('Range');
        Log::info('Range request for video', ['range' => $range]);

        if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = intval($matches[1]);
            $end = !empty($matches[2]) ? intval($matches[2]) : $size - 1;

            if ($start >= $size || $end >= $size || $start > $end) {
                return response('Requested range not satisfiable', 416, array_merge($headers, [
                    'Content-Range' => "bytes */$size"
                ]));
            }

            $length = $end - $start + 1;
            $headers['Content-Length'] = $length;
            $headers['Content-Range'] = "bytes $start-$end/$size";

            return response()->stream(function () use ($file, $start, $end) {
                $handle = fopen($file, 'rb');
                if ($handle === false) return;

                fseek($handle, $start);
                $buffer = 8192;
                $currentPosition = $start;

                while (!feof($handle) && $currentPosition <= $end) {
                    $bytesToRead = min($buffer, $end - $currentPosition + 1);
                    $data = fread($handle, $bytesToRead);
                    if ($data === false) break;
                    echo $data;
                    flush();
                    $currentPosition += strlen($data);
                }

                fclose($handle);
            }, 206, $headers);
        }
    }

    return response()->file($file, $headers);

})->name('video.serve');

// TAMBAHAN: Route khusus untuk exercise videos
Route::get('/exercise-video/{exerciseId}/{questionId}', function (Request $request, $exerciseId, $questionId) {
    Log::info('=== EXERCISE VIDEO ROUTE HIT ===', [
        'exerciseId' => $exerciseId,
        'questionId' => $questionId,
        'full_url' => $request->fullUrl(),
        'method' => $request->method()
    ]);

    try {
        // Get question and material video info
        $question = \App\Models\ExerciseQuestion::with('materialVideo')->findOrFail($questionId);

        if (!$question->materialVideo || !$question->materialVideo->video_path) {
            Log::error('Exercise video not found', [
                'questionId' => $questionId,
                'materialVideo' => $question->materialVideo
            ]);
            abort(404, 'Exercise video not found');
        }

        $materialVideo = $question->materialVideo;
        $path = $materialVideo->video_path;

        // Check if file exists in public disk
        if (!Storage::disk('public')->exists($path)) {
            Log::error('Exercise video file not found', ['path' => $path]);
            abort(404, 'Exercise video file not found');
        }

        $file = Storage::disk('public')->path($path);
        $filename = $materialVideo->video_filename ?: basename($path);

        // Extension-based mime type detection
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'video/mp4';
        $size = filesize($file);

        // FORCE CORS HEADERS
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range',
            'Access-Control-Expose-Headers' => 'Content-Range, Accept-Ranges, Content-Length',
            'X-Video-Route' => 'exercise-video-route',
            'X-Exercise-ID' => $exerciseId,
            'X-Question-ID' => $questionId,
            'X-Filename' => $filename,
        ];

        Log::info('=== SERVING EXERCISE VIDEO WITH CORS ===', [
            'file' => $file,
            'mime' => $mimeType,
            'size' => $size,
            'exercise_id' => $exerciseId,
            'question_id' => $questionId,
            'filename' => $filename
        ]);

        // Handle OPTIONS request
        if ($request->method() === 'OPTIONS') {
            Log::info('OPTIONS request for exercise video');
            return response('', 200, $headers);
        }

        // Handle range requests for video streaming
        if ($request->header('Range')) {
            $range = $request->header('Range');
            Log::info('Range request for exercise video', ['range' => $range]);

            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = !empty($matches[2]) ? intval($matches[2]) : $size - 1;

                if ($start >= $size || $end >= $size || $start > $end) {
                    return response('Requested range not satisfiable', 416, array_merge($headers, [
                        'Content-Range' => "bytes */$size"
                    ]));
                }

                $length = $end - $start + 1;
                $headers['Content-Length'] = $length;
                $headers['Content-Range'] = "bytes $start-$end/$size";

                return response()->stream(function () use ($file, $start, $end) {
                    $handle = fopen($file, 'rb');
                    if ($handle === false) return;

                    fseek($handle, $start);
                    $buffer = 8192;
                    $currentPosition = $start;

                    while (!feof($handle) && $currentPosition <= $end) {
                        $bytesToRead = min($buffer, $end - $currentPosition + 1);
                        $data = fread($handle, $bytesToRead);
                        if ($data === false) break;
                        echo $data;
                        flush();
                        $currentPosition += strlen($data);
                    }

                    fclose($handle);
                }, 206, $headers);
            }
        }

        return response()->file($file, $headers);

    } catch (\Exception $e) {
        Log::error('Exercise video route error: ' . $e->getMessage(), [
            'exerciseId' => $exerciseId,
            'questionId' => $questionId,
            'trace' => $e->getTraceAsString()
        ]);
        abort(500, 'Exercise video error: ' . $e->getMessage());
    }

})->name('exercise.video.serve');

// TAMBAHAN: Route khusus untuk quiz videos
Route::get('/quiz-video/{quizId}/{questionId}', function (Request $request, $quizId, $questionId) {
    Log::info('=== QUIZ VIDEO ROUTE HIT ===', [
        'quizId' => $quizId,
        'questionId' => $questionId,
        'full_url' => $request->fullUrl(),
        'method' => $request->method()
    ]);

    try {
        // Get question and material video info
        $question = \App\Models\QuizQuestion::with('materialVideo')->findOrFail($questionId);

        if (!$question->materialVideo || !$question->materialVideo->video_path) {
            Log::error('Quiz video not found', [
                'questionId' => $questionId,
                'materialVideo' => $question->materialVideo
            ]);
            abort(404, 'Quiz video not found');
        }

        $materialVideo = $question->materialVideo;
        $path = $materialVideo->video_path;

        // Check if file exists in public disk
        if (!Storage::disk('public')->exists($path)) {
            Log::error('Quiz video file not found', ['path' => $path]);
            abort(404, 'Quiz video file not found');
        }

        $file = Storage::disk('public')->path($path);
        $filename = $materialVideo->video_filename ?: basename($path);

        // Extension-based mime type detection
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'video/mp4';
        $size = filesize($file);

        // FORCE CORS HEADERS
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range',
            'Access-Control-Expose-Headers' => 'Content-Range, Accept-Ranges, Content-Length',
            'X-Video-Route' => 'quiz-video-route',
            'X-Quiz-ID' => $quizId,
            'X-Question-ID' => $questionId,
            'X-Filename' => $filename,
        ];

        Log::info('=== SERVING QUIZ VIDEO WITH CORS ===', [
            'file' => $file,
            'mime' => $mimeType,
            'size' => $size,
            'quiz_id' => $quizId,
            'question_id' => $questionId,
            'filename' => $filename
        ]);

        // Handle OPTIONS request
        if ($request->method() === 'OPTIONS') {
            Log::info('OPTIONS request for quiz video');
            return response('', 200, $headers);
        }

        // Handle range requests for video streaming
        if ($request->header('Range')) {
            $range = $request->header('Range');
            Log::info('Range request for quiz video', ['range' => $range]);

            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = !empty($matches[2]) ? intval($matches[2]) : $size - 1;

                if ($start >= $size || $end >= $size || $start > $end) {
                    return response('Requested range not satisfiable', 416, array_merge($headers, [
                        'Content-Range' => "bytes */$size"
                    ]));
                }

                $length = $end - $start + 1;
                $headers['Content-Length'] = $length;
                $headers['Content-Range'] = "bytes $start-$end/$size";

                return response()->stream(function () use ($file, $start, $end) {
                    $handle = fopen($file, 'rb');
                    if ($handle === false) return;

                    fseek($handle, $start);
                    $buffer = 8192;
                    $currentPosition = $start;

                    while (!feof($handle) && $currentPosition <= $end) {
                        $bytesToRead = min($buffer, $end - $currentPosition + 1);
                        $data = fread($handle, $bytesToRead);
                        if ($data === false) break;
                        echo $data;
                        flush();
                        $currentPosition += strlen($data);
                    }

                    fclose($handle);
                }, 206, $headers);
            }
        }

        return response()->file($file, $headers);

    } catch (\Exception $e) {
        Log::error('Quiz video route error: ' . $e->getMessage(), [
            'quizId' => $quizId,
            'questionId' => $questionId,
            'trace' => $e->getTraceAsString()
        ]);
        abort(500, 'Quiz video error: ' . $e->getMessage());
    }

})->name('quiz.video.serve');

// Debug route untuk quiz video
Route::get('/debug/quiz-video/{quizId}/{questionId}', function ($quizId, $questionId) {
    try {
        $question = \App\Models\QuizQuestion::with('materialVideo')->findOrFail($questionId);
        $materialVideo = $question->materialVideo;

        if (!$materialVideo) {
            return response()->json([
                'error' => 'MaterialVideo not found for question',
                'question_id' => $questionId,
                'quiz_id' => $quizId,
            ]);
        }

        $path = $materialVideo->video_path;

        return response()->json([
            'quiz_id' => $quizId,
            'question_id' => $questionId,
            'material_video_id' => $materialVideo->id,
            'video_path' => $path,
            'video_filename' => $materialVideo->video_filename,
            'exists' => Storage::disk('public')->exists($path),
            'full_path' => Storage::disk('public')->path($path),
            'size' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : 0,
            'quiz_video_url' => route('quiz.video.serve', ['quizId' => $quizId, 'questionId' => $questionId]),
            'app_url' => config('app.url'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'quiz_id' => $quizId,
            'question_id' => $questionId,
        ]);
    }
});

// Debug route untuk exercise video
Route::get('/debug/exercise-video/{exerciseId}/{questionId}', function ($exerciseId, $questionId) {
    try {
        $question = \App\Models\ExerciseQuestion::with('materialVideo')->findOrFail($questionId);
        $materialVideo = $question->materialVideo;

        if (!$materialVideo) {
            return response()->json([
                'error' => 'MaterialVideo not found for question',
                'question_id' => $questionId,
                'exercise_id' => $exerciseId,
            ]);
        }

        $path = $materialVideo->video_path;

        return response()->json([
            'exercise_id' => $exerciseId,
            'question_id' => $questionId,
            'material_video_id' => $materialVideo->id,
            'video_path' => $path,
            'video_filename' => $materialVideo->video_filename,
            'exists' => Storage::disk('public')->exists($path),
            'full_path' => Storage::disk('public')->path($path),
            'size' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : 0,
            'exercise_video_url' => route('exercise.video.serve', ['exerciseId' => $exerciseId, 'questionId' => $questionId]),
            'app_url' => config('app.url'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'exercise_id' => $exerciseId,
            'question_id' => $questionId,
        ]);
    }
});

// Debug route
Route::get('/debug/video/{materialId}/{filename}', function ($materialId, $filename) {
    $path = "material_videos/{$materialId}/{$filename}";

    return response()->json([
        'material_id' => $materialId,
        'filename' => $filename,
        'path' => $path,
        'exists' => Storage::disk('public')->exists($path),
        'full_path' => Storage::disk('public')->path($path),
        'size' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : 0,
        'video_url' => route('video.serve', ['materialId' => $materialId, 'filename' => $filename]),
        'app_url' => config('app.url'),
    ]);
});
