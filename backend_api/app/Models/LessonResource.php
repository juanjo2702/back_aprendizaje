<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonResource extends Model
{
    use HasFactory;

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
}

