<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Support\Collection;

class UserPresentationService
{
    public function authPayload(User $user): array
    {
        $user->loadMissing([
            'profile.equippedAvatarFrameItem.shopItem',
            'profile.equippedProfileTitleItem.shopItem',
            'equippedItems.shopItem',
        ]);

        return array_merge($user->only([
            'id',
            'name',
            'email',
            'avatar',
            'role',
            'bio',
            'total_points',
            'total_coins',
            'current_streak',
            'last_active_at',
        ]), [
            'current_level' => $user->current_level,
            'level_title' => $user->level_title,
            'headline' => $user->profile?->headline,
            'mini_bio' => $user->profile?->mini_bio,
            'location' => $user->profile?->location,
            'equipped_avatar_frame' => $this->serializeFrame($this->equippedItem($user, 'avatar_frame')),
            'equipped_profile_title' => $this->serializeTitle($this->equippedItem($user, 'profile_title')),
            'equipped_profile_titles' => $this->serializeTitles($this->equippedItems($user, 'profile_title', 3)),
        ]);
    }

    public function commentAuthorPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'role' => $user->role,
            'level' => $user->current_level,
            'level_title' => $user->level_title,
            'equipped_avatar_frame' => $this->serializeFrame($this->equippedItem($user, 'avatar_frame')),
            'equipped_profile_title' => $this->serializeTitle($this->equippedItem($user, 'profile_title')),
            'equipped_profile_titles' => $this->serializeTitles($this->equippedItems($user, 'profile_title', 3)),
        ];
    }

    public function miniProfile(User $user): array
    {
        $user->loadMissing([
            'profile.equippedAvatarFrameItem.shopItem',
            'profile.equippedProfileTitleItem.shopItem',
            'badges' => fn ($query) => $query->select('badges.id', 'name', 'icon', 'description')
                ->latest('user_badges.created_at')
                ->limit(3),
        ]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'level' => $user->current_level,
            'level_title' => $user->level_title,
            'streak' => (int) $user->current_streak,
            'headline' => $user->profile?->headline,
            'mini_bio' => $user->profile?->mini_bio,
            'location' => $user->profile?->location,
            'equipped_avatar_frame' => $this->serializeFrame($this->equippedItem($user, 'avatar_frame')),
            'equipped_profile_title' => $this->serializeTitle($this->equippedItem($user, 'profile_title')),
            'equipped_profile_titles' => $this->serializeTitles($this->equippedItems($user, 'profile_title', 3)),
            'top_badges' => $user->badges->map(fn (Badge $badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'icon' => $badge->icon,
                'description' => $badge->description,
            ])->values()->all(),
        ];
    }

    public function equippedItem(User $user, string $type): ?UserItem
    {
        return $this->equippedItems($user, $type, 1)->first();
    }

    public function equippedItems(User $user, string $type, ?int $limit = null): Collection
    {
        if ($user->relationLoaded('profile') && $user->profile) {
            $item = match ($type) {
                'avatar_frame' => $user->profile->equippedAvatarFrameItem,
                'profile_title' => $user->profile->equippedProfileTitleItem,
                default => null,
            };

            if ($item) {
                if ($type === 'profile_title') {
                    $items = $user->relationLoaded('equippedItems')
                        ? $user->equippedItems
                            ->where('item_type', $type)
                            ->sortByDesc('updated_at')
                            ->values()
                        : $user->equippedItems()
                            ->where('item_type', $type)
                            ->with('shopItem')
                            ->orderByDesc('updated_at')
                            ->get();

                    $items = $items->prepend($item)->unique('id')->values();

                    return $limit ? $items->take($limit)->values() : $items;
                }

                return collect([$item]);
            }
        }

        if ($user->relationLoaded('equippedItems')) {
            $items = $user->equippedItems
                ->where('item_type', $type)
                ->sortByDesc('updated_at')
                ->values();

            return $limit ? $items->take($limit)->values() : $items;
        }

        $query = $user->equippedItems()
            ->where('item_type', $type)
            ->with('shopItem')
            ->orderByDesc('updated_at');

        return $limit ? $query->limit($limit)->get() : $query->get();
    }

    public function serializeInventoryItem(UserItem $item): array
    {
        return [
            'id' => $item->id,
            'item_type' => $item->item_type,
            'is_equipped' => (bool) $item->is_equipped,
            'is_used' => (bool) $item->is_used,
            'acquired_at' => $item->acquired_at,
            'used_at' => $item->used_at,
            'metadata' => $item->metadata ?? [],
            'shop_item' => $item->shopItem ? [
                'id' => $item->shopItem->id,
                'name' => $item->shopItem->name,
                'description' => $item->shopItem->description,
                'type' => $item->shopItem->type,
                'metadata' => $item->shopItem->metadata ?? [],
            ] : null,
            'frame' => $this->serializeFrame($item->item_type === 'avatar_frame' ? $item : null),
            'title' => $this->serializeTitle($item->item_type === 'profile_title' ? $item : null),
        ];
    }

    public function serializeFrame(?UserItem $item): ?array
    {
        if (! $item) {
            return null;
        }

        $metadata = array_merge($item->shopItem?->metadata ?? [], $item->metadata ?? []);

        return [
            'user_item_id' => $item->id,
            'shop_item_id' => $item->shop_item_id,
            'name' => $item->shopItem?->name,
            'frame_class' => $metadata['frame_class'] ?? $metadata['frame_style'] ?? 'frame-default',
            'frame_svg' => $metadata['frame_svg'] ?? null,
            'accent_color' => $metadata['accent_color'] ?? null,
        ];
    }

    public function serializeTitle(?UserItem $item): ?array
    {
        if (! $item) {
            return null;
        }

        $metadata = array_merge($item->shopItem?->metadata ?? [], $item->metadata ?? []);

        return [
            'user_item_id' => $item->id,
            'shop_item_id' => $item->shop_item_id,
            'name' => $item->shopItem?->name,
            'label' => $metadata['title'] ?? $item->shopItem?->name,
            'color' => $metadata['title_color'] ?? null,
        ];
    }

    public function serializeTitles(iterable $items): array
    {
        return collect($items)
            ->map(fn (UserItem $item) => $this->serializeTitle($item))
            ->filter()
            ->values()
            ->all();
    }
}
