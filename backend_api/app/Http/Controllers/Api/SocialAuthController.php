<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirige al proveedor OAuth.
     */
    public function redirect($provider)
    {
        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Maneja el callback del proveedor OAuth.
     */
    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Si el usuario existe pero no tiene el provider linkeado, lo actualizamos.
                if (! $user->provider_id) {
                    $user->update([
                        'provider_name' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'provider_token' => $socialUser->token,
                    ]);
                }
            } else {
                // Si el usuario es nuevo, lo registramos.
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_token' => $socialUser->token,
                    'password' => Hash::make(Str::random(24)), // Random password para SSO
                ]);
            }

            // Revocamos tokens antiguos o simplemente creamos uno nuevo (estilo SPA persistente)
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirigir al frontend con el token (Frontend URL debe estar en config, o harcoded de ser dev)
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:9002');

            return redirect()->away("{$frontendUrl}/auth/social-callback?token={$token}&user=".urlencode(json_encode($user)));

        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo iniciar sesión con '.ucfirst($provider).'. '.$e->getMessage()], 401);
        }
    }
}
