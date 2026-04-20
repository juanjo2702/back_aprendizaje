<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\PlatformSetting;

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
        'total_coins',
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
            'total_coins' => 'integer',
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

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'instructor_id');
    }

    public function approvedPayouts()
    {
        return $this->hasMany(Payout::class, 'approved_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
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

    public function getCurrentLevelAttribute(): int
    {
        $points = (int) ($this->total_points ?? 0);
        $curve = PlatformSetting::getValue('gamification.levels', PlatformSetting::defaultLevelCurve());
        $currentLevel = 1;

        foreach ((array) $curve as $row) {
            if ($points >= (int) ($row['xp_required'] ?? 0)) {
                $currentLevel = max($currentLevel, (int) ($row['level'] ?? 1));
            }
        }

        return $currentLevel;
    }

    public function getEarnedCoinsAttribute(): int
    {
        return (int) (($this->total_coins ?? 0) + $this->spent_coins);
    }

    public function getSpentCoinsAttribute(): int
    {
        return (int) $this->purchases()
            ->whereIn('status', ['completed', 'consumed'])
            ->sum('cost_coins');
    }

    public function getAvailableCoinsAttribute(): int
    {
        return max(0, (int) ($this->total_coins ?? 0));
    }

    public function getLevelTitleAttribute(): string
    {
        $curve = PlatformSetting::getValue('gamification.levels', PlatformSetting::defaultLevelCurve());
        $currentRow = collect((array) $curve)
            ->firstWhere('level', $this->current_level);

        return $currentRow['title'] ?? 'Aprendiz';
    }
}
