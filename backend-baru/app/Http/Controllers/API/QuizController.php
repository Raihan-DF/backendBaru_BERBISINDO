<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizOption;
use App\Models\StudentProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    // Konstanta untuk disk storage
    private const STORAGE_DISK = 'public';
    private const VIDEO_FOLDER = 'quiz_videos';

    // Metode lain tetap sama...

    public function storeQuestion(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $quiz->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'question_type' => 'required|string|in:multiple_choice,true_false',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:200000', // 200MB max
            'points' => 'required|integer|min:1',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $videoPath = null;

        if ($request->hasFile('video')) {
            try {
                $videoFile = $request->file('video');

                // Dapatkan informasi file
                $extension = $videoFile->getClientOriginalExtension();

                // Buat nama file yang unik
                $filename = time() . '_' . Str::slug(pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;

                // Buat path folder
                $folderPath = self::VIDEO_FOLDER . '/' . $id;

                // Pastikan direktori ada
                if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                    Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                }

                // Simpan file
                $path = Storage::disk(self::STORAGE_DISK)->putFileAs($folderPath, $videoFile, $filename);

                // Log informasi file untuk debugging
                Log::info('Quiz question video uploaded', [
                    'original_name' => $videoFile->getClientOriginalName(),
                    'extension' => $extension,
                    'mime_type' => $videoFile->getMimeType(),
                    'path' => $path,
                    'size' => $videoFile->getSize()
                ]);

                $videoPath = $path;
            } catch (\Exception $e) {
                Log::error('Quiz question video upload error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Video upload failed: ' . $e->getMessage()], 500);
            }
        }

        // Get the highest order
        $order = QuizQuestion::where('quiz_id', $id)->max('order') + 1;

        $question = QuizQuestion::create([
            'quiz_id' => $id,
            'question' => $request->question,
            'question_type' => $request->question_type,
            'video_path' => $videoPath,
            'points' => $request->points,
            'order' => $order,
        ]);

        // Create options
        foreach ($request->options as $option) {
            QuizOption::create([
                'quiz_question_id' => $question->id,
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'],
            ]);
        }

        return response()->json($question->load('options'), 201);
    }

    public function updateQuestion(Request $request, $id, $questionId)
    {
        $quiz = Quiz::findOrFail($id);
        $question = QuizQuestion::where('quiz_id', $id)
            ->where('id', $questionId)
            ->firstOrFail();

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $quiz->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'question_type' => 'required|string|in:multiple_choice,true_false',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:200000', // 200MB max
            'points' => 'required|integer|min:1',
            'options' => 'required|array|min:2',
            'options.*.id' => 'nullable|exists:quiz_options,id',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('video')) {
            try {
                // Hapus video lama jika ada
                if ($question->video_path && Storage::disk(self::STORAGE_DISK)->exists($question->video_path)) {
                    Storage::disk(self::STORAGE_DISK)->delete($question->video_path);
                }

                $videoFile = $request->file('video');

                // Dapatkan informasi file
                $extension = $videoFile->getClientOriginalExtension();

                // Buat nama file yang unik
                $filename = time() . '_' . Str::slug(pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;

                // Buat path folder
                $folderPath = self::VIDEO_FOLDER . '/' . $id;

                // Pastikan direktori ada
                if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                    Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                }

                // Simpan file
                $path = Storage::disk(self::STORAGE_DISK)->putFileAs($folderPath, $videoFile, $filename);

                // Log informasi file untuk debugging
                Log::info('Quiz question video updated', [
                    'original_name' => $videoFile->getClientOriginalName(),
                    'extension' => $extension,
                    'mime_type' => $videoFile->getMimeType(),
                    'path' => $path,
                    'size' => $videoFile->getSize()
                ]);

                $question->video_path = $path;
            } catch (\Exception $e) {
                Log::error('Quiz question video update error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Video update failed: ' . $e->getMessage()], 500);
            }
        }

        $question->question = $request->question;
        $question->question_type = $request->question_type;
        $question->points = $request->points;
        $question->save();

        // Update options
        $existingOptionIds = [];
        foreach ($request->options as $optionData) {
            if (isset($optionData['id'])) {
                // Update existing option
                $option = QuizOption::findOrFail($optionData['id']);
                $option->option_text = $optionData['option_text'];
                $option->is_correct = $optionData['is_correct'];
                $option->save();
                $existingOptionIds[] = $option->id;
            } else {
                // Create new option
                $option = QuizOption::create([
                    'quiz_question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'is_correct' => $optionData['is_correct'],
                ]);
                $existingOptionIds[] = $option->id;
            }
        }

        // Delete options that are not in the request
        QuizOption::where('quiz_question_id', $question->id)
            ->whereNotIn('id', $existingOptionIds)
            ->delete();

        return response()->json($question->load('options'));
    }

    public function deleteQuestion(Request $request, $id, $questionId)
    {
        $quiz = Quiz::findOrFail($id);
        $question = QuizQuestion::where('quiz_id', $id)
            ->where('id', $questionId)
            ->firstOrFail();

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $quiz->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Hapus video jika ada
        if ($question->video_path && Storage::disk(self::STORAGE_DISK)->exists($question->video_path)) {
            Storage::disk(self::STORAGE_DISK)->delete($question->video_path);
        }

        // Delete options
        QuizOption::where('quiz_question_id', $questionId)->delete();

        // Delete question
        $question->delete();

        return response()->json(['message' => 'Question deleted successfully']);
    }

    public function streamQuestionVideo(Request $request, $id, $questionId)
    {
        $quiz = Quiz::findOrFail($id);
        $question = QuizQuestion::where('quiz_id', $id)
            ->where('id', $questionId)
            ->firstOrFail();

        // Check if quiz is published or user is the creator
        if (!$quiz->is_published && $request->user()->id !== $quiz->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$question->video_path || !Storage::disk(self::STORAGE_DISK)->exists($question->video_path)) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $file = Storage::disk(self::STORAGE_DISK)->path($question->video_path);

        // Deteksi MIME type dari ekstensi file
        $extension = pathinfo($question->video_path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeTypeFromExtension($extension);

        $size = Storage::disk(self::STORAGE_DISK)->size($question->video_path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="' . basename($question->video_path) . '"',
        ];

        // Handle range requests for video streaming
        if ($request->header('Range')) {
            return $this->handleRangeRequest($request, $file, $size, $mimeType);
        }

        return response()->file($file, $headers);
    }

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

    private function getMimeTypeFromExtension($extension)
    {
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
            'webm' => 'video/webm',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'video/mp4';
    }

    // Metode lain tetap sama...
}
