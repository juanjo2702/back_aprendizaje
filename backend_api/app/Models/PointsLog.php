<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'points',
        'source',
        'source_id',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
