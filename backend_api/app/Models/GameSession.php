<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_config_id',
        'course_id',
        'module_id',
        'lesson_id',
        'score',
        'time_spent',
        'attempt',
        'status',
        'started_at',
        'completed_at',
        'game_data',
        'details',
    ];

    protected $casts = [
        'game_data' => 'array',
        'details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gameConfiguration()
    {
        return $this->belongsTo(GameConfiguration::class, 'game_config_id');
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
}
