<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'type',
        'content_url',
        'content_text',
        'duration',
        'sort_order',
        'is_free',
        'game_config_id',
        'quiz_id',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'is_free' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            Module::class,
            'id',        // modules.id
            'id',        // courses.id
            'module_id', // lessons.module_id
            'course_id'  // modules.course_id
        );
    }

    public function gameConfiguration()
    {
        return $this->belongsTo(GameConfiguration::class, 'game_config_id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
