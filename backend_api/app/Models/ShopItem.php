<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'cost_coins',
        'minimum_level_required',
        'course_id',
        'lesson_id',
        'created_by',
        'stock',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cost_coins' => 'integer',
            'minimum_level_required' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function userItems()
    {
        return $this->hasMany(UserItem::class);
    }

    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class);
    }
}
