<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LessonResource extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'lesson_id',
        'title',
        'file_name',
        'file_url',
        'mime_type',
        'file_size_bytes',
        'is_downloadable',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'is_downloadable' => 'boolean',
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
        $this->addMediaCollection('lesson_resource')->singleFile();
    }

    public function latestResourceMedia()
    {
        return $this->getFirstMedia('lesson_resource');
    }

    public function signedDownloadUrl(int $minutes = 30): ?string
    {
        $media = $this->latestResourceMedia();

        if (! $media) {
            return $this->file_url;
        }

        $signedPath = URL::temporarySignedRoute(
            'protected-media.show',
            now()->addMinutes($minutes),
            [
                'media' => $media->id,
                'filename' => $media->file_name,
            ],
            false
        );

        return rtrim(config('app.url') ?: url('/'), '/').$signedPath;
    }
}
