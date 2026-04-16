<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'bio',
        'total_points',
        'current_streak',
        'last_active_at',
        'provider_name',
        'provider_id',
        'provider_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'provider_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot('progress')
            ->withTimestamps();
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')->withTimestamps();
    }

    public function pointsLog()
    {
        return $this->hasMany(PointsLog::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(UserQuizAttempt::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function interactiveActivityResults()
    {
        return $this->hasMany(InteractiveActivityResult::class);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }
}
