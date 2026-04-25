<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\UserItem;
use App\Models\UserProfile;
use App\Services\BadgeService;
use App\Services\UserPresentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentInventoryController extends Controller
{
    public function __construct(
        private readonly UserPresentationService $userPresentationService,
        private readonly BadgeService $badgeService
    ) {
    }

    public function index(Request $request)
    {
        $this->badgeService->checkGeneralBadges($request->user());

        $user = $request->user()->fresh()->load([
            'userItems.shopItem',
            'userCoupons.shopItem',
            'userCoupons.userItem',
            'equippedItems.shopItem',
            'profile.equippedAvatarFrameItem.shopItem',
            'profile.equippedProfileTitleItem.shopItem',
        ]);

        $frames = $user->userItems
            ->where('item_type', 'avatar_frame')
            ->values()
            ->map(fn (UserItem $item) => $this->userPresentationService->serializeInventoryItem($item))
            ->all();

        $titles = $user->userItems
            ->where('item_type', 'profile_title')
            ->values()
            ->map(fn (UserItem $item) => $this->userPresentationService->serializeInventoryItem($item))
            ->all();

        $extras = $user->userItems
            ->where('item_type', 'premium_content')
            ->values()
            ->map(fn (UserItem $item) => $this->userPresentationService->serializeInventoryItem($item))
            ->all();

        $coupons = $user->userCoupons
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (UserCoupon $coupon) => $this->serializeCoupon($coupon))
            ->all();

        return response()->json([
            'economy' => $this->economyPayload($user),
            'equipped' => [
                'frame' => $this->userPresentationService->serializeFrame(
                    $this->userPresentationService->equippedItem($user, 'avatar_frame')
                ),
                'title' => $this->userPresentationService->serializeTitle(
                    $this->userPresentationService->equippedItem($user, 'profile_title')
                ),
                'titles' => $this->userPresentationService->serializeTitles(
                    $this->userPresentationService->equippedItems($user, 'profile_title', 3)
                ),
            ],
            'mini_profile' => $this->userPresentationService->miniProfile($user),
            'locker' => [
                'frames' => $frames,
                'titles' => $titles,
                'extras' => $extras,
                'coupons' => $coupons,
            ],
        ]);
    }

    public function equip(Request $request)
    {
        $validated = $request->validate([
            'user_item_id' => 'required|integer|exists:user_items,id',
            'equipped' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $item = UserItem::query()
            ->with('shopItem')
            ->where('user_id', $user->id)
            ->findOrFail($validated['user_item_id']);

        if (! in_array($item->item_type, ['avatar_frame', 'profile_title'], true)) {
            return response()->json([
                'message' => 'Solo puedes equipar marcos y títulos desde el vestidor.',
            ], 422);
        }

        $shouldEquip = array_key_exists('equipped', $validated)
            ? (bool) $validated['equipped']
            : ! $item->is_equipped;

        DB::transaction(function () use ($item, $user, $shouldEquip) {
            if ($item->item_type === 'avatar_frame' && $shouldEquip) {
                UserItem::query()
                    ->where('user_id', $user->id)
                    ->where('item_type', $item->item_type)
                    ->where('id', '!=', $item->id)
                    ->where('is_equipped', true)
                    ->update(['is_equipped' => false]);
            }

            if ($item->item_type === 'profile_title' && $shouldEquip) {
                $equippedTitlesCount = UserItem::query()
                    ->where('user_id', $user->id)
                    ->where('item_type', 'profile_title')
                    ->where('id', '!=', $item->id)
                    ->where('is_equipped', true)
                    ->count();

                if ($equippedTitlesCount >= 3) {
                    throw ValidationException::withMessages([
                        'user_item_id' => 'Puedes mostrar hasta 3 títulos al mismo tiempo. Quita uno antes de equipar otro.',
                    ]);
                }
            }

            $item->forceFill(['is_equipped' => $shouldEquip])->save();

            $profile = UserProfile::query()->firstOrCreate(['user_id' => $user->id]);
            if ($item->item_type === 'avatar_frame') {
                $profile->equipped_avatar_frame_item_id = $shouldEquip ? $item->id : null;
            }
            if ($item->item_type === 'profile_title') {
                $primaryEquippedTitleId = UserItem::query()
                    ->where('user_id', $user->id)
                    ->where('item_type', 'profile_title')
                    ->where('is_equipped', true)
                    ->orderByDesc('updated_at')
                    ->value('id');

                $profile->equipped_profile_title_item_id = $primaryEquippedTitleId;
            }
            $profile->save();
        });

        $freshUser = $user->fresh([
            'equippedItems.shopItem',
            'profile.equippedAvatarFrameItem.shopItem',
            'profile.equippedProfileTitleItem.shopItem',
        ]);
        $freshItem = $item->fresh(['shopItem']);

        return response()->json([
            'message' => $shouldEquip ? 'Ítem equipado correctamente.' : 'Ítem quitado del perfil.',
            'item' => $this->userPresentationService->serializeInventoryItem($freshItem),
            'equipped' => [
                'frame' => $this->userPresentationService->serializeFrame(
                    $this->userPresentationService->equippedItem($freshUser, 'avatar_frame')
                ),
                'title' => $this->userPresentationService->serializeTitle(
                    $this->userPresentationService->equippedItem($freshUser, 'profile_title')
                ),
                'titles' => $this->userPresentationService->serializeTitles(
                    $this->userPresentationService->equippedItems($freshUser, 'profile_title', 3)
                ),
            ],
            'auth_user' => $this->userPresentationService->authPayload($freshUser),
        ]);
    }

    public function miniProfile(User $user)
    {
        $user->loadMissing('equippedItems.shopItem');

        return response()->json([
            'profile' => $this->userPresentationService->miniProfile($user),
        ]);
    }

    private function economyPayload(User $user): array
    {
        return [
            'level' => $user->current_level,
            'level_title' => $user->level_title,
            'total_xp' => (int) $user->total_points,
            'earned_coins' => $user->earned_coins,
            'spent_coins' => $user->spent_coins,
            'available_coins' => $user->available_coins,
            'streak' => (int) $user->current_streak,
        ];
    }

    private function serializeCoupon(UserCoupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_percent' => (float) $coupon->discount_percent,
            'is_used' => (bool) $coupon->is_used,
            'used_at' => $coupon->used_at,
            'metadata' => $coupon->metadata ?? [],
            'shop_item' => $coupon->shopItem ? [
                'id' => $coupon->shopItem->id,
                'name' => $coupon->shopItem->name,
                'description' => $coupon->shopItem->description,
                'type' => $coupon->shopItem->type,
            ] : null,
        ];
    }
}
