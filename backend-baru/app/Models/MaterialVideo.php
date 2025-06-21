<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MaterialVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'title',
        'description',
        'video_path',
        'video_filename',
        'video_type',
        'order',
    ];

    protected $appends = ['video_url', 'stream_url', 'direct_url'];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function getVideoUrlAttribute()
    {
        if ($this->video_path) {
            // TIDAK PERLU DIUBAH - tetap menggunakan asset() yang akan di-intercept oleh web route
            return asset('storage/' . str_replace('public/', '', $this->video_path));
        }

        return null;
    }

    public function getStreamUrlAttribute()
    {
        if ($this->video_path) {
            return route('api.materials.videos.stream', [
                'materialId' => $this->material_id,
                'videoId' => $this->id
            ]);
        }

        return null;
    }

    public function getDirectUrlAttribute()
    {
        // Tambahan: Direct URL yang sama dengan video_url untuk compatibility
        return $this->video_url;
    }

    public function getVideoInfoAttribute()
    {
        if (!$this->video_path) {
            return null;
        }

        return [
            'filename' => $this->video_filename,
            'type' => $this->video_type,
            'size' => Storage::exists($this->video_path) ? Storage::size($this->video_path) : 0,
            'url' => $this->video_url,
            'stream_url' => $this->stream_url,
        ];
    }
}
