<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'user_answer',
        'is_correct',
        'points_earned',
    ];

    protected $casts = [
        'user_answer' => 'array',
        'is_correct' => 'boolean',
    ];

    public function attempt()
    {
        return $this->belongsTo(UserQuizAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
