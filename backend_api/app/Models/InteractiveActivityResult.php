<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractiveActivityResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'module_id',
        'lesson_id',
        'interactive_config_id',
        'source_type',
        'source_id',
        'score',
        'max_score',
        'xp_awarded',
        'badges_awarded',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'xp_awarded' => 'integer',
            'badges_awarded' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function interactiveConfig()
    {
        return $this->belongsTo(InteractiveConfig::class);
    }
}

