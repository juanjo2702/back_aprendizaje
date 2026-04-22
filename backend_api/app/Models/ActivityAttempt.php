<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityAttempt extends Model
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
        'max_score',
        'score_percentage',
        'passing_score',
        'xp_awarded',
        'xp_penalty',
        'coin_awarded',
        'passed',
        'locked',
        'payload',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'xp_awarded' => 'integer',
            'xp_penalty' => 'integer',
            'coin_awarded' => 'integer',
            'passed' => 'boolean',
            'locked' => 'boolean',
            'payload' => 'array',
            'attempted_at' => 'datetime',
        ];
    }
}
