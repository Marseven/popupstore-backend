<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'role_id' => 2, // customer
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Merge session cart if exists
        if ($request->has('session_id')) {
            $this->cartService->mergeSessionCart($request->session_id, $user->id);
        }

        return response()->json([
            'message' => 'Inscription réussie',
            'user' => $user->load('role'),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Find user by email or phone
        $user = User::where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé',
            ], 403);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        // Merge session cart if exists
        if ($request->has('session_id')) {
            $this->cartService->mergeSessionCart($request->session_id, $user->id);
        }

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user->load('role'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('role'),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Mot de passe actuel incorrect',
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        // Revoke all tokens except current
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès',
        ]);
    }
}
