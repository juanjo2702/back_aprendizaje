<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Models\UserCoupon;
use App\Models\UserItem;
use App\Services\UserPresentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function __construct(
        private readonly UserPresentationService $userPresentationService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $ownedItems = $user->userItems()
            ->selectRaw('shop_item_id, COUNT(*) as total_owned')
            ->groupBy('shop_item_id')
            ->pluck('total_owned', 'shop_item_id');

        $items = ShopItem::query()
            ->with(['course:id,title,slug', 'lesson:id,title,module_id', 'lesson.module:id,course_id,title'])
            ->where('is_active', true)
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderBy('cost_coins')
            ->get()
            ->map(function (ShopItem $item) use ($user, $ownedItems) {
                $ownedCount = (int) ($ownedItems[$item->id] ?? 0);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'description' => $item->description,
                    'type' => $item->type,
                    'cost_coins' => $item->cost_coins,
                    'minimum_level_required' => $item->minimum_level_required,
                    'metadata' => $item->metadata,
                    'stock' => $item->stock,
                    'course' => $item->course ? $item->course->only(['id', 'title', 'slug']) : null,
                    'lesson' => $item->lesson ? [
                        'id' => $item->lesson->id,
                        'title' => $item->lesson->title,
                        'module_title' => $item->lesson->module?->title,
                    ] : null,
                    'already_owned' => $ownedCount > 0 && in_array($item->type, ['avatar_frame', 'profile_title', 'premium_content'], true),
                    'owned_count' => $ownedCount,
                    'can_afford' => $user->available_coins >= $item->cost_coins,
                    'locked_by_level' => $user->current_level < $item->minimum_level_required,
                ];
            })
            ->values();

        return response()->json([
            'economy' => $this->economyPayload($user),
            'items' => $items,
        ]);
    }

    public function purchases(Request $request)
    {
        $user = $request->user();

        $purchases = $user->purchases()
            ->with(['shopItem.course:id,title,slug', 'shopItem.lesson:id,title', 'userItem.coupon'])
            ->latest('purchased_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'economy' => $this->economyPayload($user),
            'data' => $purchases->items(),
            'current_page' => $purchases->currentPage(),
            'last_page' => $purchases->lastPage(),
            'total' => $purchases->total(),
        ]);
    }

    public function purchase(Request $request, ShopItem $shopItem)
    {
        $user = $request->user();

        if (! $shopItem->is_active) {
            return response()->json(['message' => 'Este artículo ya no está disponible.'], 422);
        }

        if ($user->current_level < $shopItem->minimum_level_required) {
            return response()->json([
                'message' => "Necesitas nivel {$shopItem->minimum_level_required} para comprar este artículo.",
            ], 403);
        }

        if ($user->available_coins < $shopItem->cost_coins) {
            return response()->json(['message' => 'No tienes monedas suficientes.'], 422);
        }

        if ($shopItem->stock !== null && $shopItem->purchases()->count() >= $shopItem->stock) {
            return response()->json(['message' => 'Este artículo ya agotó su stock.'], 422);
        }

        $alreadyOwned = UserItem::query()
            ->where('user_id', $user->id)
            ->where('shop_item_id', $shopItem->id)
            ->exists();

        if ($alreadyOwned && in_array($shopItem->type, ['avatar_frame', 'profile_title', 'premium_content'], true)) {
            return response()->json(['message' => 'Ya posees este artículo.'], 422);
        }

        $payload = DB::transaction(function () use ($user, $shopItem) {
            $metadata = match ($shopItem->type) {
                'discount_coupon' => [
                    'coupon_code' => strtoupper(($shopItem->metadata['code_prefix'] ?? 'SAVE').'-'.Str::random(8)),
                    'discount_percent' => $shopItem->metadata['discount_percent'] ?? 10,
                ],
                'profile_title' => [
                    'title' => $shopItem->metadata['title'] ?? $shopItem->name,
                    'title_color' => $shopItem->metadata['title_color'] ?? null,
                ],
                'avatar_frame' => [
                    'frame_class' => $shopItem->metadata['frame_class'] ?? $shopItem->metadata['frame_style'] ?? 'frame-default',
                    'frame_svg' => $shopItem->metadata['frame_svg'] ?? null,
                    'accent_color' => $shopItem->metadata['accent_color'] ?? null,
                ],
                default => $shopItem->metadata ?? [],
            };

            $purchase = Purchase::create([
                'user_id' => $user->id,
                'shop_item_id' => $shopItem->id,
                'cost_coins' => $shopItem->cost_coins,
                'status' => 'completed',
                'metadata' => $metadata,
                'purchased_at' => now(),
            ]);
            $user->decrement('total_coins', $shopItem->cost_coins);

            $userItem = UserItem::create([
                'user_id' => $user->id,
                'shop_item_id' => $shopItem->id,
                'purchase_id' => $purchase->id,
                'item_type' => $shopItem->type,
                'metadata' => $metadata,
                'acquired_at' => now(),
            ]);

            $coupon = null;

            if ($shopItem->type === 'discount_coupon') {
                $coupon = UserCoupon::create([
                    'user_id' => $user->id,
                    'shop_item_id' => $shopItem->id,
                    'user_item_id' => $userItem->id,
                    'code' => $metadata['coupon_code'],
                    'discount_percent' => $metadata['discount_percent'],
                    'metadata' => [
                        'label' => $shopItem->name,
                    ],
                ]);
            }

            return [
                'purchase' => $purchase,
                'user_item' => $userItem,
                'coupon' => $coupon,
            ];
        });

        return response()->json([
            'message' => 'Compra realizada correctamente.',
            'economy' => $this->economyPayload($user->fresh()),
            'purchase' => $payload['purchase']->load(['shopItem', 'userItem.coupon']),
            'inventory_item' => $this->userPresentationService->serializeInventoryItem(
                $payload['user_item']->load('shopItem')
            ),
            'coupon' => $payload['coupon'],
        ], 201);
    }

    private function economyPayload($user): array
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
}
