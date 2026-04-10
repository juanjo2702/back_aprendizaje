<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'default_config',
    ];

    protected $casts = [
        'default_config' => 'array',
    ];

    public function gameConfigurations()
    {
        return $this->hasMany(GameConfiguration::class);
    }
}
