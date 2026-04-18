<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
        'contentable_type',
        'contentable_id',
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

    public function contentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function videoContent()
    {
        return $this->hasOne(LessonVideo::class);
    }

    public function readingContent()
    {
        return $this->hasOne(LessonReading::class);
    }

    public function resourceContent()
    {
        return $this->hasOne(LessonResource::class);
    }

    public function interactiveConfig()
    {
        return $this->hasOne(InteractiveConfig::class);
    }

    public function userProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function interactiveResults()
    {
        return $this->hasMany(InteractiveActivityResult::class);
    }

    public function gameConfiguration()
    {
        return $this->belongsTo(GameConfiguration::class, 'game_config_id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class);
    }

    // ─── Domain Helpers ─────────────────────────────────────

    public function getNormalizedTypeAttribute(): string
    {
        return match ($this->type) {
            'text' => 'reading',
            'game', 'quiz' => 'interactive',
            default => $this->type,
        };
    }

    public function isInteractive(): bool
    {
        return in_array($this->normalized_type, ['interactive', 'game', 'quiz'], true);
    }

    public function resolvesTo(string $modelClass): bool
    {
        return $this->contentable_type === $modelClass;
    }
}
