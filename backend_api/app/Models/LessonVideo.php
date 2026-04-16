<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'provider',
        'video_url',
        'embed_url',
        'duration_seconds',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}

