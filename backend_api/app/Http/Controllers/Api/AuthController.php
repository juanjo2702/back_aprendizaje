<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserPresentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserPresentationService $userPresentationService
    ) {
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $this->userPresentationService->authPayload($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login an existing user.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Revoke previous tokens to keep only one active session
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $this->userPresentationService->authPayload($user),
            'token' => $token,
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request)
    {
        return response()->json(
            $this->userPresentationService->authPayload($request->user())
        );
    }

    /**
     * Update authenticated user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'sometimes|nullable|string|max:1000',
            'avatar' => 'sometimes|nullable|string|max:500',
            'headline' => 'sometimes|nullable|string|max:255',
            'mini_bio' => 'sometimes|nullable|string|max:500',
            'location' => 'sometimes|nullable|string|max:120',
        ]);

        $user->update(collect($validated)->only(['name', 'bio', 'avatar'])->all());
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($validated)->only(['headline', 'mini_bio', 'location'])->all()
        );

        return response()->json(
            $this->userPresentationService->authPayload($user->fresh())
        );
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
