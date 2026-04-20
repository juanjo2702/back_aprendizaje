<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
    ];

    public function getDecodedValueAttribute(): mixed
    {
        $value = $this->attributes['value'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->decoded_value ?? $default;
    }

    public static function putValue(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = 'json',
        ?string $description = null
    ): self {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'description' => $description,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    public static function defaultLevelCurve(): array
    {
        return [
            ['level' => 1, 'xp_required' => 0, 'title' => 'Aprendiz'],
            ['level' => 2, 'xp_required' => 250, 'title' => 'Explorador'],
            ['level' => 3, 'xp_required' => 500, 'title' => 'Explorador'],
            ['level' => 4, 'xp_required' => 750, 'title' => 'Explorador'],
            ['level' => 5, 'xp_required' => 1000, 'title' => 'Veterano'],
            ['level' => 6, 'xp_required' => 1250, 'title' => 'Veterano'],
            ['level' => 7, 'xp_required' => 1500, 'title' => 'Veterano'],
            ['level' => 8, 'xp_required' => 1750, 'title' => 'Veterano'],
            ['level' => 9, 'xp_required' => 2000, 'title' => 'Maestro'],
            ['level' => 10, 'xp_required' => 2250, 'title' => 'Maestro'],
            ['level' => 11, 'xp_required' => 2500, 'title' => 'Maestro'],
            ['level' => 12, 'xp_required' => 2750, 'title' => 'Maestro'],
        ];
    }
}
