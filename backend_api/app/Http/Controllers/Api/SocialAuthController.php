<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * @var array<int, string>
     */
    private array $allowedProviders = ['google', 'github'];

    /**
     * Redirige al proveedor OAuth.
     */
    public function redirect(string $provider): JsonResponse
    {
        if (! in_array($provider, $this->allowedProviders, true)) {
            return response()->json(['message' => 'Proveedor no soportado.'], 404);
        }

        if (! $this->hasProviderCredentials($provider)) {
            return response()->json([
                'message' => 'Faltan credenciales OAuth para '.ucfirst($provider).'. Configura variables de entorno.',
            ], 422);
        }

        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Maneja el callback del proveedor OAuth.
     */
    public function callback(string $provider)
    {
        if (! in_array($provider, $this->allowedProviders, true)) {
            return response()->json(['message' => 'Proveedor no soportado.'], 404);
        }

        if (! $this->hasProviderCredentials($provider)) {
            return $this->redirectWithSocialError($provider, 'Faltan credenciales OAuth en backend.');
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Si el usuario existe pero no tiene el provider linkeado, o tiene rol nulo, lo actualizamos.
                $updates = [];
                if (! $user->provider_id) {
                    $updates['provider_name'] = $provider;
                    $updates['provider_id'] = $socialUser->getId();
                    $updates['provider_token'] = $socialUser->token;
                }
                if (! $user->role) {
                    $updates['role'] = 'student';
                }
                
                if (!empty($updates)) {
                    $user->update($updates);
                }
            } else {
                // Si el usuario es nuevo, lo registramos.
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'role' => 'student',
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_token' => $socialUser->token,
                    'password' => Hash::make(Str::random(24)), // Random password para SSO
                ]);
            }

            // Revocamos tokens antiguos o simplemente creamos uno nuevo (estilo SPA persistente)
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirigir al frontend con token y datos de usuario seguros
            $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:9000'), '/');
            $safeUser = $user->fresh()->only([
                'id',
                'name',
                'email',
                'avatar',
                'role',
                'bio',
                'total_points',
                'current_streak',
                'last_active_at',
            ]);

            return redirect()->away("{$frontendUrl}/auth/social-callback?token={$token}&user=".urlencode(json_encode($safeUser)));

        } catch (\Exception $e) {
            return $this->redirectWithSocialError($provider, 'No se pudo iniciar sesión. '.$e->getMessage());
        }
    }

    private function hasProviderCredentials(string $provider): bool
    {
        $id = $this->readProviderCredential($provider, 'client_id');
        $secret = $this->readProviderCredential($provider, 'client_secret');
        $redirect = $this->readProviderCredential($provider, 'redirect');

        return $id !== '' && $secret !== '' && $redirect !== '';
    }

    private function readProviderCredential(string $provider, string $key): string
    {
        $configValue = trim((string) config("services.{$provider}.{$key}"));
        if ($configValue !== '') {
            return $configValue;
        }

        $prefix = strtoupper($provider);
        $envKey = match ($key) {
            'client_id' => "{$prefix}_CLIENT_ID",
            'client_secret' => "{$prefix}_CLIENT_SECRET",
            'redirect' => "{$prefix}_REDIRECT_URI",
            default => null,
        };

        if (! $envKey) {
            return '';
        }

        $envValue = getenv($envKey);
        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }

        $serverValue = $_SERVER[$envKey] ?? '';
        if (is_string($serverValue) && trim($serverValue) !== '') {
            return trim($serverValue);
        }

        $globalEnvValue = $_ENV[$envKey] ?? '';

        return is_string($globalEnvValue) ? trim($globalEnvValue) : '';
    }

    private function redirectWithSocialError(string $provider, string $message)
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:9000'), '/');
        $error = urlencode($message);
        $provider = urlencode($provider);

        return redirect()->away("{$frontendUrl}/login?social_error={$error}&provider={$provider}");
    }
}
