<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    // ========================
    // REGISTER
    // ========================
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone'    => ['nullable', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'language' => ['nullable', 'string', 'in:fr,en'],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'language' => $request->language ?? 'fr',
        ]);

        event(new Registered($user));

        // Welcome points
        $this->loyaltyService->awardPoints($user, 100, 'bonus', 'Points de bienvenue !');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => __('Compte créé avec succès. Veuillez vérifier votre email.'),
            'user'    => $user->fresh(),
            'token'   => $token,
        ], 201);
    }

    // ========================
    // LOGIN
    // ========================
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('Les identifiants fournis sont incorrects.')],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->isBanned()) {
            Auth::logout();
            return response()->json(['message' => 'Compte suspendu: ' . $user->ban_reason], 403);
        }

        // Check 2FA
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return response()->json([
                'requires_2fa' => true,
                'user_id'      => $user->id,
            ], 200);
        }

        // Revoke old tokens
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $user->load('defaultAddress'),
            'token'   => $token,
        ]);
    }

    // ========================
    // VERIFY 2FA
    // ========================
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'code'    => ['required', 'string'],
        ]);

        $user = User::findOrFail($request->user_id);

        // Verify the TOTP code
        $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
        $valid = $google2fa->verifyKey(decrypt($user->two_factor_secret), $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Code invalide'], 422);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    // ========================
    // LOGOUT
    // ========================
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie']);
    }

    // ========================
    // ME
    // ========================
    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load(['defaultAddress', 'badges'])
        );
    }

    // ========================
    // FORGOT PASSWORD
    // ========================
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    // ========================
    // RESET PASSWORD
    // ========================
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    // ========================
    // VERIFY EMAIL
    // ========================
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string)$hash, sha1($user->email))) {
            return response()->json(['message' => 'Lien de vérification invalide'], 400);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['message' => 'Email vérifié avec succès']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email déjà vérifié']);
        }
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Email de vérification envoyé']);
    }

    // ========================
    // 2FA MANAGEMENT
    // ========================
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
        $secret = $google2fa->generateSecretKey();
        $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json(['qr_code' => $qrCodeUrl, 'secret' => $secret]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required']]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Mot de passe incorrect'], 422);
        }

        $request->user()->forceFill([
            'two_factor_secret'       => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => 'Authentification à deux facteurs désactivée']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['token' => $token]);
    }
}
