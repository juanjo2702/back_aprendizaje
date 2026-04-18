<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LessonVideo extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

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

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('lesson_video')->singleFile();
    }

    public function latestVideoMedia()
    {
        return $this->getFirstMedia('lesson_video');
    }

    public function signedStreamUrl(int $minutes = 30): ?string
    {
        $media = $this->latestVideoMedia();

        if (! $media) {
            return $this->video_url;
        }

        return URL::temporarySignedRoute(
            'protected-media.show',
            now()->addMinutes($minutes),
            [
                'media' => $media->id,
                'filename' => $media->file_name,
            ]
        );
    }
}
