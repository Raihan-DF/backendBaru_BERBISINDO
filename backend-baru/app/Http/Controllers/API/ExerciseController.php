<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\StudentProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ExerciseController extends Controller
{
    // Konstanta untuk disk storage
    private const STORAGE_DISK = 'public';
    private const VIDEO_FOLDER = 'exercise_videos';

    public function index(Request $request)
    {
        $query = Exercise::with(['creator', 'material']);

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
            });
        }

        return response()->json($exercises);
    }

    public function store(Request $request)
    {
        // Check if user is a teacher
        if (!$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:200000', // 200MB max
            'material_id' => 'nullable|exists:materials,id',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'is_published' => 'boolean',
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
                $folderPath = self::VIDEO_FOLDER;

                // Pastikan direktori ada
                if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                    Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                }

                // Simpan file
                $path = Storage::disk(self::STORAGE_DISK)->putFileAs($folderPath, $videoFile, $filename);

                // Log informasi file untuk debugging
                Log::info('Exercise video uploaded', [
                    'original_name' => $videoFile->getClientOriginalName(),
                    'extension' => $extension,
                    'mime_type' => $videoFile->getMimeType(),
                    'path' => $path,
                    'size' => $videoFile->getSize()
                ]);

                $videoPath = $path;
            } catch (\Exception $e) {
                Log::error('Exercise video upload error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Video upload failed: ' . $e->getMessage()], 500);
            }
        }

        $exercise = Exercise::create([
            'title' => $request->title,
            'description' => $request->description,
            'video_path' => $videoPath,
            'material_id' => $request->material_id,
            'created_by' => $request->user()->id,
            'difficulty_level' => $request->difficulty_level,
            'is_published' => $request->is_published ?? false,
        ]);

        return response()->json($exercise, 201);
    }

    public function show(Request $request, $id)
    {
        $exercise = Exercise::with(['creator', 'material'])->findOrFail($id);

        // Check if exercise is published or user is the creator
        if (!$exercise->is_published && $request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If user is a student, add progress information
        if ($request->user()->isStudent()) {
            $progress = StudentProgress::where('user_id', $request->user()->id)
                ->where('exercise_id', $exercise->id)
                ->first();

            $exercise->is_completed = $progress ? $progress->is_completed : false;
        }

        return response()->json($exercise);
    }

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
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:200000', // 200MB max
            'material_id' => 'nullable|exists:materials,id',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'is_published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('video')) {
            try {
                // Hapus video lama jika ada
                if ($exercise->video_path && Storage::disk(self::STORAGE_DISK)->exists($exercise->video_path)) {
                    Storage::disk(self::STORAGE_DISK)->delete($exercise->video_path);
                }

                $videoFile = $request->file('video');

                // Dapatkan informasi file
                $extension = $videoFile->getClientOriginalExtension();

                // Buat nama file yang unik
                $filename = time() . '_' . Str::slug(pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;

                // Buat path folder
                $folderPath = self::VIDEO_FOLDER;

                // Pastikan direktori ada
                if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                    Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                }

                // Simpan file
                $path = Storage::disk(self::STORAGE_DISK)->putFileAs($folderPath, $videoFile, $filename);

                // Log informasi file untuk debugging
                Log::info('Exercise video updated', [
                    'original_name' => $videoFile->getClientOriginalName(),
                    'extension' => $extension,
                    'mime_type' => $videoFile->getMimeType(),
                    'path' => $path,
                    'size' => $videoFile->getSize()
                ]);

                $exercise->video_path = $path;
            } catch (\Exception $e) {
                Log::error('Exercise video update error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Video update failed: ' . $e->getMessage()], 500);
            }
        }

        $exercise->title = $request->title;
        $exercise->description = $request->description;
        $exercise->material_id = $request->material_id;
        $exercise->difficulty_level = $request->difficulty_level;
        $exercise->is_published = $request->is_published ?? $exercise->is_published;
        $exercise->save();

        return response()->json($exercise);
    }

    public function destroy(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Hapus video jika ada
        if ($exercise->video_path && Storage::disk(self::STORAGE_DISK)->exists($exercise->video_path)) {
            Storage::disk(self::STORAGE_DISK)->delete($exercise->video_path);
        }

        $exercise->delete();

        return response()->json(['message' => 'Exercise deleted successfully']);
    }

    public function stream(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        // Check if exercise is published or user is the creator
        if (!$exercise->is_published && $request->user()->id !== $exercise->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$exercise->video_path || !Storage::disk(self::STORAGE_DISK)->exists($exercise->video_path)) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $file = Storage::disk(self::STORAGE_DISK)->path($exercise->video_path);

        // Deteksi MIME type dari ekstensi file
        $extension = pathinfo($exercise->video_path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeTypeFromExtension($extension);

        $size = Storage::disk(self::STORAGE_DISK)->size($exercise->video_path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="' . basename($exercise->video_path) . '"',
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

    public function markAsCompleted(Request $request, $id)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $exercise = Exercise::findOrFail($id);

        // Check if exercise is published
        if (!$exercise->is_published) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $progress = StudentProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'exercise_id' => $id,
                'progress_type' => 'exercise',
            ],
            [
                'is_completed' => true,
                'completed_at' => now(),
            ]
        );

        return response()->json(['message' => 'Exercise marked as completed']);
    }
}
