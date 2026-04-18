<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'body_markdown',
        'body_html',
        'estimated_minutes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'estimated_minutes' => 'integer',
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
}
