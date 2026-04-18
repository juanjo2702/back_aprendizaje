<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\ShopItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $items = ShopItem::query()
            ->with(['course:id,title,slug', 'lesson:id,title,module_id', 'lesson.module:id,course_id,title'])
            ->where('is_active', true)
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderBy('cost_coins')
            ->get()
            ->map(function (ShopItem $item) use ($user) {
                $purchasesCount = $item->purchases()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['completed', 'consumed'])
                    ->count();

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
                    'already_owned' => $purchasesCount > 0 && in_array($item->type, ['avatar_frame', 'profile_title', 'premium_content'], true),
                    'owned_count' => $purchasesCount,
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
            ->with(['shopItem.course:id,title,slug', 'shopItem.lesson:id,title'])
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

        $alreadyOwned = Purchase::query()
            ->where('user_id', $user->id)
            ->where('shop_item_id', $shopItem->id)
            ->whereIn('status', ['completed', 'consumed'])
            ->exists();

        if ($alreadyOwned && in_array($shopItem->type, ['avatar_frame', 'profile_title', 'premium_content'], true)) {
            return response()->json(['message' => 'Ya posees este artículo.'], 422);
        }

        $purchase = DB::transaction(function () use ($user, $shopItem) {
            $metadata = match ($shopItem->type) {
                'discount_coupon' => [
                    'coupon_code' => strtoupper(($shopItem->metadata['code_prefix'] ?? 'SAVE').'-'.Str::random(8)),
                    'discount_percent' => $shopItem->metadata['discount_percent'] ?? 10,
                ],
                'profile_title' => [
                    'title' => $shopItem->metadata['title'] ?? $shopItem->name,
                ],
                'avatar_frame' => [
                    'frame_style' => $shopItem->metadata['frame_style'] ?? 'default-frame',
                ],
                default => $shopItem->metadata ?? [],
            };

            return Purchase::create([
                'user_id' => $user->id,
                'shop_item_id' => $shopItem->id,
                'cost_coins' => $shopItem->cost_coins,
                'status' => 'completed',
                'metadata' => $metadata,
                'purchased_at' => now(),
            ]);
        });

        $user->decrement('total_coins', $shopItem->cost_coins);

        return response()->json([
            'message' => 'Compra realizada correctamente.',
            'economy' => $this->economyPayload($user->fresh()),
            'purchase' => $purchase->load('shopItem'),
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
