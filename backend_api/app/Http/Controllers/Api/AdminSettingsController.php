<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function show()
    {
        return response()->json($this->payload());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'finance.platform_commission_percentage' => 'required|numeric|min:0|max:100',
            'finance.currency' => 'nullable|string|max:8',
            'finance.minimum_payout_amount' => 'nullable|numeric|min:0|max:999999',
            'gamification.levels' => 'required|array|min:1',
            'gamification.levels.*.level' => 'required|integer|min:1|max:99',
            'gamification.levels.*.xp_required' => 'required|integer|min:0|max:99999999',
            'gamification.levels.*.title' => 'required|string|max:80',
        ]);

        $levels = collect($validated['gamification']['levels'])
            ->sortBy('level')
            ->values()
            ->all();

        PlatformSetting::putValue(
            'finance.platform_commission_percentage',
            (float) $validated['finance']['platform_commission_percentage'],
            'finance',
            'number',
            'Porcentaje de comisión global de la plataforma.'
        );
        PlatformSetting::putValue(
            'finance.currency',
            $validated['finance']['currency'] ?? 'BOB',
            'finance',
            'string',
            'Moneda por defecto de la plataforma.'
        );
        PlatformSetting::putValue(
            'finance.minimum_payout_amount',
            (float) ($validated['finance']['minimum_payout_amount'] ?? 0),
            'finance',
            'number',
            'Monto mínimo para solicitar retiro.'
        );
        PlatformSetting::putValue(
            'gamification.levels',
            $levels,
            'gamification',
            'json',
            'Curva global de niveles y XP.'
        );

        AdminActivityLog::record($request->user(), 'platform.settings_updated', 'Configuración global', [
            'finance' => $validated['finance'],
            'levels_count' => count($levels),
        ]);

        return response()->json([
            'message' => 'Configuración global actualizada correctamente.',
            'settings' => $this->payload(),
        ]);
    }

    private function payload(): array
    {
        return [
            'finance' => [
                'platform_commission_percentage' => (float) PlatformSetting::getValue('finance.platform_commission_percentage', 20),
                'currency' => (string) PlatformSetting::getValue('finance.currency', 'BOB'),
                'minimum_payout_amount' => (float) PlatformSetting::getValue('finance.minimum_payout_amount', 0),
            ],
            'gamification' => [
                'levels' => PlatformSetting::getValue('gamification.levels', PlatformSetting::defaultLevelCurve()),
            ],
        ];
    }
}
