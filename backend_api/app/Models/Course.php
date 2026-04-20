<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'price',
        'thumbnail',
        'promo_video',
        'instructor_id',
        'category_id',
        'level',
        'language',
        'status',
        'submitted_for_review_at',
        'published_at',
        'approved_by',
        'review_notes',
        'requirements',
        'what_you_learn',
        'has_certificate',
        'certificate_min_score',
        'minimum_level_required',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'requirements' => 'array',
            'what_you_learn' => 'array',
            'has_certificate' => 'boolean',
            'certificate_min_score' => 'integer',
            'minimum_level_required' => 'integer',
            'submitted_for_review_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot('progress')
            ->withTimestamps();
    }

    public function gameConfigurations()
    {
        return $this->hasMany(GameConfiguration::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function interactiveConfigs()
    {
        return $this->hasMany(InteractiveConfig::class);
    }

    public function interactiveActivityResults()
    {
        return $this->hasMany(InteractiveActivityResult::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    // ─── Accessors ───────────────────────────────────────────

    public function getTotalDurationAttribute(): int
    {
        return $this->lessons()->sum('duration');
    }

    public function getTotalLessonsAttribute(): int
    {
        return $this->lessons()->count();
    }

    public function getTotalStudentsAttribute(): int
    {
        return $this->enrollments()->count();
    }

    public function hasInteractiveActivities(): bool
    {
        return $this->lessons()
            ->whereIn('type', ['interactive', 'game', 'quiz'])
            ->exists();
    }
}
