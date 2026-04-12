<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private array $allowedProviders = ['google', 'facebook', 'apple'];

    public function __construct(private LoyaltyService $loyaltyService) {}

    public function redirect(string $provider): JsonResponse
    {
        if (!in_array($provider, $this->allowedProviders)) {
            return response()->json(['message' => 'Fournisseur non supporté'], 422);
        }

        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();
        return response()->json(['url' => $url]);
    }

    public function callback(string $provider): JsonResponse
    {
        if (!in_array($provider, $this->allowedProviders)) {
            return response()->json(['message' => 'Fournisseur non supporté'], 422);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentification sociale échouée'], 422);
        }

        return $this->findOrCreateUser($socialUser, $provider);
    }

    // For mobile: receive the token from the app and validate it
    public function loginWithToken(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'in:google,facebook,apple'],
            'token'    => ['required', 'string'],
            'name'     => ['nullable', 'string'],
            'email'    => ['nullable', 'email'],
        ]);

        try {
            $socialUser = Socialite::driver($request->provider)->stateless()->userFromToken($request->token);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token invalide'], 422);
        }

        return $this->findOrCreateUser($socialUser, $request->provider);
    }

    private function findOrCreateUser($socialUser, string $provider): JsonResponse
    {
        $providerField = $provider . '_id';

        // Try to find existing user
        $user = User::where($providerField, $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        $isNew = false;

        if (!$user) {
            $user = User::create([
                'name'         => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Utilisateur',
                'email'        => $socialUser->getEmail(),
                $providerField => $socialUser->getId(),
                'provider'     => $provider,
                'avatar'       => $socialUser->getAvatar(),
                'password'     => Hash::make(Str::random(24)),
                'email_verified_at' => now(),
            ]);
            $isNew = true;
            $this->loyaltyService->awardPoints($user, 100, 'bonus', 'Points de bienvenue !');
        } else {
            // Update provider id if not set
            if (!$user->$providerField) {
                $user->update([$providerField => $socialUser->getId(), 'provider' => $provider]);
            }
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'Compte suspendu'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'   => $user,
            'token'  => $token,
            'is_new' => $isNew,
        ], $isNew ? 201 : 200);
    }
}
