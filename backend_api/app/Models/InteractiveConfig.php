<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractiveConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'course_id',
        'module_id',
        'authoring_mode',
        'activity_type',
        'config_payload',
        'assets_manifest',
        'source_package_path',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'config_payload' => 'array',
            'assets_manifest' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function activityResults()
    {
        return $this->hasMany(InteractiveActivityResult::class);
    }
}

