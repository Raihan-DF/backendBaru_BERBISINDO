<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialVideo;
use App\Models\StudentProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class MaterialVideoController extends Controller
{
    // Konstanta untuk disk storage
    protected const STORAGE_DISK = 'public';
    protected const VIDEO_FOLDER = 'material_videos';

    public function index(Request $request, $materialId)
    {
        $material = Material::findOrFail($materialId);

        // Check if material is published or user is the creator
        if (!$material->is_published && $request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $videos = MaterialVideo::where('material_id', $materialId)
            ->orderBy('order')
            ->get();

        // If user is a student, add progress information
        if ($request->user()->isStudent()) {
            $videos->each(function ($video) use ($request) {
                $progress = StudentProgress::where('user_id', $request->user()->id)
                    ->where('material_video_id', $video->id)
                    ->first();

                $video->is_completed = $progress ? $progress->is_completed : false;
            });
        }

        return response()->json($videos);
    }

    public function store(Request $request, $materialId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'video' => 'required|file|max:200000', //|mimes:mp4,mov,avi,wmv
                'order' => 'nullable|integer',
            ]);

            $material = Material::findOrFail($materialId);

            if ($request->hasFile('video')) {
                $videoFile = $request->file('video');

                // Dapatkan informasi file
                $originalName = $videoFile->getClientOriginalName();
                $extension = $videoFile->getClientOriginalExtension();
                $mimeType = $videoFile->getMimeType();

                // Buat nama file yang unik
                $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

                // Buat path folder
                $folderPath = self::VIDEO_FOLDER . '/' . $materialId;
                $fullPath = $folderPath . '/' . $filename;

                // Pastikan direktori ada
                if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                    Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
                }

                // Simpan file
                $path = Storage::disk(self::STORAGE_DISK)->putFileAs($folderPath, $videoFile, $filename);

                // Log informasi file untuk debugging
                Log::info('Video uploaded', [
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'path' => $path,
                    'size' => $videoFile->getSize()
                ]);

                // Validasi ekstensi file
                $extension = strtolower($videoFile->getClientOriginalExtension());
                $allowedExtensions = ['mp4', 'mov', 'avi', 'wmv'];
                if (!in_array($extension, $allowedExtensions)) {
                    return response()->json(['error' => 'Format file tidak didukung. Gunakan MP4, MOV, AVI, atau WMV.'], 422);
                }

                $video = new MaterialVideo([
                    'title' => $request->title,
                    'description' => $request->description,
                    'video_path' => $path,
                    'video_filename' => $originalName,
                    'video_type' => $mimeType,
                    'order' => $request->order,
                ]);

                $material->videos()->save($video);

                // Tambahkan URL video ke respons
                $video->video_url = $video->getVideoUrlAttribute();

                return response()->json($video, 201);
            } else {
                return response()->json(['error' => 'No video uploaded'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Video store error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $materialId, $videoId)
    {
        $material = Material::findOrFail($materialId);
        $video = MaterialVideo::where('material_id', $materialId)
            ->where('id', $videoId)
            ->firstOrFail();

        // Check if material is published or user is the creator
        if (!$material->is_published && $request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If user is a student, mark video as viewed
        if ($request->user()->isStudent()) {
            $progress = StudentProgress::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'material_id' => $materialId,
                    'material_video_id' => $videoId,
                    'progress_type' => 'material_video',
                ],
                [
                    'is_completed' => false,
                ]
            );
        }

        return response()->json($video);
    }

    public function update(Request $request, $materialId, $videoId)
    {
        $material = Material::findOrFail($materialId);
        $video = MaterialVideo::where('material_id', $materialId)
            ->where('id', $videoId)
            ->firstOrFail();

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:200000', // 200MB max
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('video')) {
            // Hapus video lama
            if ($video->video_path && Storage::disk(self::STORAGE_DISK)->exists($video->video_path)) {
                Storage::disk(self::STORAGE_DISK)->delete($video->video_path);
            }

            $videoFile = $request->file('video');

            // Dapatkan informasi file
            $originalName = $videoFile->getClientOriginalName();
            $extension = $videoFile->getClientOriginalExtension();
            $mimeType = $videoFile->getMimeType();

            // Buat nama file yang unik
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            // Buat path folder
            $folderPath = self::VIDEO_FOLDER . '/' . $materialId;
            $fullPath = $folderPath . '/' . $filename;

            // Pastikan direktori ada
            if (!Storage::disk(self::STORAGE_DISK)->exists($folderPath)) {
                Storage::disk(self::STORAGE_DISK)->makeDirectory($folderPath);
            }

            // Simpan file
            $path = Storage::disk(self::STORAGE_DISK)->putFileAs($folderPath, $videoFile, $filename);

            // Log informasi file untuk debugging
            Log::info('Video updated', [
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'path' => $path,
                'size' => $videoFile->getSize()
            ]);

            $video->video_path = $path;
            $video->video_filename = $originalName;
            $video->video_type = $mimeType;
        }

        $video->title = $request->title;
        $video->description = $request->description;
        if ($request->has('order')) {
            $video->order = $request->order;
        }
        $video->save();

        return response()->json($video);
    }

    public function destroy(Request $request, $materialId, $videoId)
    {
        $material = Material::findOrFail($materialId);
        $video = MaterialVideo::where('material_id', $materialId)
            ->where('id', $videoId)
            ->firstOrFail();

        // Check if user is the creator or a teacher
        if ($request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Hapus file video
        if ($video->video_path && Storage::disk(self::STORAGE_DISK)->exists($video->video_path)) {
            Storage::disk(self::STORAGE_DISK)->delete($video->video_path);
        }

        $video->delete();

        return response()->json(['message' => 'Video deleted successfully']);
    }

    public function stream(Request $request, $materialId, $videoId)
    {
        $material = Material::findOrFail($materialId);
        $video = MaterialVideo::where('material_id', $materialId)
            ->where('id', $videoId)
            ->firstOrFail();

        // Check if material is published or user is the creator
        if (!$material->is_published && $request->user()->id !== $material->created_by && !$request->user()->isTeacher()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$video->video_path || !Storage::disk(self::STORAGE_DISK)->exists($video->video_path)) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $file = Storage::disk(self::STORAGE_DISK)->path($video->video_path);
        $mimeType = $video->video_type ?: 'video/mp4';
        $size = Storage::disk(self::STORAGE_DISK)->size($video->video_path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="' . $video->video_filename . '"',
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

    public function markAsCompleted(Request $request, $materialId, $videoId)
    {
        // Check if user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $material = Material::findOrFail($materialId);
        $video = MaterialVideo::where('material_id', $materialId)
            ->where('id', $videoId)
            ->firstOrFail();

        // Check if material is published
        if (!$material->is_published) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $progress = StudentProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'material_id' => $materialId,
                'material_video_id' => $videoId,
                'progress_type' => 'material_video',
            ],
            [
                'is_completed' => true,
                'completed_at' => now(),
            ]
        );

        // Check if all videos in the material are completed
        $totalVideos = $material->videos()->count();
        $completedVideos = StudentProgress::where('user_id', $request->user()->id)
            ->where('material_id', $materialId)
            ->where('progress_type', 'material_video')
            ->where('is_completed', true)
            ->count();

        if ($totalVideos > 0 && $totalVideos === $completedVideos) {
            // Mark the material as completed
            StudentProgress::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'material_id' => $materialId,
                    'progress_type' => 'material',
                ],
                [
                    'is_completed' => true,
                    'completed_at' => now(),
                ]
            );

            // Check for achievements
            $this->checkAchievements($request->user()->id);
        }

        return response()->json(['message' => 'Video marked as completed']);
    }

    private function checkAchievements($userId)
    {
        // Count completed materials
        $completedMaterials = StudentProgress::where('user_id', $userId)
            ->where('progress_type', 'material')
            ->where('is_completed', true)
            ->count();

        // Check for material completion achievements
        $achievements = \App\Models\Achievement::where('achievement_type', 'materials_completed')
            ->where('required_count', '<=', $completedMaterials)
            ->get();

        foreach ($achievements as $achievement) {
            // Check if the user already has this achievement
            $exists = \App\Models\StudentAchievement::where('user_id', $userId)
                ->where('achievement_id', $achievement->id)
                ->exists();

            if (!$exists) {
                // Award the achievement
                \App\Models\StudentAchievement::create([
                    'user_id' => $userId,
                    'achievement_id' => $achievement->id,
                    'achieved_at' => now(),
                ]);

                // Create a notification
                \App\Models\Notification::create([
                    'user_id' => $userId,
                    'title' => 'Pencapaian Baru!',
                    'message' => 'Selamat! Anda telah mendapatkan pencapaian: ' . $achievement->title,
                    'type' => 'success',
                    'link' => '/student/achievements',
                ]);
            }
        }
    }
}
