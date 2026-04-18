<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'module_id',
        'lesson_id',
        'interactive_config_id',
        'attempt_number',
        'score',
        'passing_score',
        'xp_awarded',
        'coin_awarded',
        'reward_multiplier',
        'status',
        'payload',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'passing_score' => 'integer',
            'xp_awarded' => 'integer',
            'coin_awarded' => 'integer',
            'reward_multiplier' => 'decimal:2',
            'payload' => 'array',
            'attempted_at' => 'datetime',
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
