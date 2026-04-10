<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'game_type_id',
        'course_id',
        'module_id',
        'lesson_id',
        'config',
        'max_score',
        'time_limit',
        'max_attempts',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    public function gameType()
    {
        return $this->belongsTo(GameType::class);
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

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }
}
