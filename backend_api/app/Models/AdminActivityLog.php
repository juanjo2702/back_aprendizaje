<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class AdminActivityLog extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'target_label',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(?User $actor, string $action, EloquentModel|string|null $target = null, array $metadata = []): self
    {
        $targetType = null;
        $targetId = null;
        $targetLabel = null;

        if ($target instanceof EloquentModel) {
            $targetType = $target::class;
            $targetId = $target->getKey();
            $targetLabel = $target->title
                ?? $target->name
                ?? $target->transaction_id
                ?? $target->slug
                ?? class_basename($target).'#'.$target->getKey();
        } elseif (is_string($target)) {
            $targetLabel = $target;
        }

        return static::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_label' => $targetLabel,
            'metadata' => $metadata,
        ]);
    }
}
