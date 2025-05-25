<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'video_path',
        'material_id',
        'created_by',
        'difficulty_level',
        'is_published',
    ];

    protected $appends = ['video_url', 'stream_url'];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function getVideoUrlAttribute()
    {
        if ($this->video_path) {
            return asset('storage/' . str_replace('public/', '', $this->video_path));
        }
        return null;
    }

    public function getStreamUrlAttribute()
    {
        if ($this->video_path) {
            return route('api.exercises.stream', ['id' => $this->id]);
        }
        return null;
    }

    public function getVideoInfoAttribute()
    {
        if (!$this->video_path) {
            return null;
        }

        // Ekstrak informasi dari path
        $extension = pathinfo($this->video_path, PATHINFO_EXTENSION);
        $mimeType = $this->getMimeTypeFromExtension($extension);

        return [
            'filename' => basename($this->video_path),
            'type' => $mimeType,
            'size' => Storage::exists($this->video_path) ? Storage::size($this->video_path) : 0,
            'url' => $this->video_url,
            'stream_url' => $this->stream_url,
        ];
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
}
