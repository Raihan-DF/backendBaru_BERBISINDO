<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question',
        'question_type',
        'video_path',
        'points',
        'order',
    ];

    protected $appends = ['video_url', 'stream_url'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options()
    {
        return $this->hasMany(QuizOption::class);
    }

    public function correctOption()
    {
        return $this->options()->where('is_correct', true)->first();
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
            return route('api.quiz.questions.stream', ['id' => $this->quiz_id, 'questionId' => $this->id]);
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
