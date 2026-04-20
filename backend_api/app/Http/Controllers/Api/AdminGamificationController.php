<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Badge;
use App\Models\ShopItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminGamificationController extends Controller
{
    public function badges()
    {
        return response()->json(
            Badge::query()->latest()->get()
        );
    }

    public function storeBadge(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'type' => 'required|in:course_completion,streak,points,game_master,speedster',
            'criteria' => 'required|array',
        ]);

        $badge = Badge::query()->create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
        ]);

        AdminActivityLog::record($request->user(), 'badge.created', $badge);

        return response()->json($badge, 201);
    }

    public function updateBadge(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'type' => 'sometimes|in:course_completion,streak,points,game_master,speedster',
            'criteria' => 'sometimes|array',
        ]);

        $badge->update($validated);

        AdminActivityLog::record($request->user(), 'badge.updated', $badge);

        return response()->json($badge->fresh());
    }

    public function destroyBadge(Request $request, Badge $badge)
    {
        AdminActivityLog::record($request->user(), 'badge.deleted', $badge);
        $badge->delete();

        return response()->json(['message' => 'Insignia eliminada correctamente.']);
    }

    public function rewards()
    {
        return response()->json(
            ShopItem::query()
                ->with(['course:id,title', 'lesson:id,title', 'creator:id,name'])
                ->latest()
                ->get()
        );
    }

    public function storeReward(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:discount_coupon,premium_content,avatar_frame,profile_title',
            'cost_coins' => 'required|integer|min:0|max:999999',
            'minimum_level_required' => 'nullable|integer|min:1|max:99',
            'course_id' => 'nullable|exists:courses,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'stock' => 'nullable|integer|min:0|max:999999',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        $reward = ShopItem::query()->create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            'minimum_level_required' => (int) ($validated['minimum_level_required'] ?? 1),
            'created_by' => $request->user()->id,
        ]);

        AdminActivityLog::record($request->user(), 'reward.created', $reward);

        return response()->json($reward->fresh(['course:id,title', 'lesson:id,title', 'creator:id,name']), 201);
    }

    public function updateReward(Request $request, ShopItem $shopItem)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:discount_coupon,premium_content,avatar_frame,profile_title',
            'cost_coins' => 'sometimes|integer|min:0|max:999999',
            'minimum_level_required' => 'nullable|integer|min:1|max:99',
            'course_id' => 'nullable|exists:courses,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'stock' => 'nullable|integer|min:0|max:999999',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        $shopItem->update($validated);

        AdminActivityLog::record($request->user(), 'reward.updated', $shopItem);

        return response()->json($shopItem->fresh(['course:id,title', 'lesson:id,title', 'creator:id,name']));
    }

    public function destroyReward(Request $request, ShopItem $shopItem)
    {
        AdminActivityLog::record($request->user(), 'reward.deleted', $shopItem);
        $shopItem->delete();

        return response()->json(['message' => 'Recompensa eliminada correctamente.']);
    }
}
