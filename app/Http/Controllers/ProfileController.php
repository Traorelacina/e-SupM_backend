<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/profile
    // ─────────────────────────────────────────────────────────────
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['defaultAddress', 'badges']));
    }

    // ─────────────────────────────────────────────────────────────
    // PUT /api/profile
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'                     => ['nullable', 'string', 'max:255'],
            'phone'                    => ['nullable', 'string', 'max:20', 'unique:users,phone,' . $request->user()->id],
            'date_of_birth'            => ['nullable', 'date', 'before:today'],
            'gender'                   => ['nullable', 'in:male,female,other'],
            'language'                 => ['nullable', 'in:fr,en'],
            'notification_preferences' => ['nullable', 'array'],
        ]);

        $request->user()->update($request->only([
            'name', 'phone', 'date_of_birth', 'gender', 'language', 'notification_preferences',
        ]));

        return response()->json([
            'message' => 'Profil mis à jour',
            'user'    => $request->user()->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/profile/avatar
    // ─────────────────────────────────────────────────────────────
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);
        $path = $request->file('avatar')->store('avatars', 'public');
        $request->user()->update(['avatar' => $path]);
        return response()->json([
            'message' => 'Photo de profil mise à jour',
            'avatar'  => asset('storage/' . $path),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PUT /api/profile/password
    // ─────────────────────────────────────────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect'], 422);
        }

        $request->user()->update(['password' => Hash::make($request->password)]);
        $request->user()->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json(['message' => 'Mot de passe modifié avec succès']);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE /api/profile
    // ─────────────────────────────────────────────────────────────
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required']]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Mot de passe incorrect'], 422);
        }

        $request->user()->tokens()->delete();
        $request->user()->delete();
        return response()->json(['message' => 'Compte supprimé']);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/addresses
    // ─────────────────────────────────────────────────────────────
    public function addresses(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($addresses);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/addresses
    // ─────────────────────────────────────────────────────────────
    public function storeAddress(Request $request): JsonResponse
    {
        $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'address'     => ['required', 'string', 'max:255'],
            'city'        => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone'       => ['required', 'string', 'max:20'],
            'is_default'  => ['nullable', 'boolean'],
        ]);

        // Si nouvelle adresse par défaut → retirer l'ancienne
        if ($request->boolean('is_default')) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        // Si c'est la première adresse → la mettre par défaut automatiquement
        $isFirst = $request->user()->addresses()->count() === 0;

        $address = $request->user()->addresses()->create([
            'label'       => $request->label,
            'address'     => $request->address,
            'city'        => $request->city,
            'postal_code' => $request->postal_code,
            'phone'       => $request->phone,
            'is_default'  => $request->boolean('is_default') || $isFirst,
        ]);

        return response()->json([
            'message' => 'Adresse ajoutée',
            'address' => $address,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    // PUT /api/addresses/{id}
    // ─────────────────────────────────────────────────────────────
    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $request->validate([
            'label'       => ['nullable', 'string', 'max:100'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'is_default'  => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            $request->user()->addresses()
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $address->update($request->only([
            'label', 'address', 'city', 'postal_code', 'phone', 'is_default',
        ]));

        return response()->json([
            'message' => 'Adresse mise à jour',
            'address' => $address->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE /api/addresses/{id}
    // ─────────────────────────────────────────────────────────────
    public function destroyAddress(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;
        $address->delete();

        // Si on supprime l'adresse par défaut → promouvoir la suivante
        if ($wasDefault) {
            $next = $request->user()->addresses()->orderByDesc('created_at')->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Adresse supprimée']);
    }

    // ─────────────────────────────────────────────────────────────
    // PUT /api/addresses/{id}/default
    // ─────────────────────────────────────────────────────────────
    public function setDefaultAddress(Request $request, int $id): JsonResponse
    {
        // Vérifier que l'adresse appartient à l'utilisateur
        $request->user()->addresses()->findOrFail($id);

        $request->user()->addresses()->update(['is_default' => false]);
        $request->user()->addresses()->where('id', $id)->update(['is_default' => true]);

        return response()->json(['message' => 'Adresse par défaut mise à jour']);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/my-stats/monthly
    // ─────────────────────────────────────────────────────────────
    public function monthlyStats(Request $request): JsonResponse
    {
        $stats = $request->user()
            ->orders()
            ->where('payment_status', 'paid')
            ->whereYear('created_at', now()->year)
            ->selectRaw('MONTH(created_at) as month, SUM(total) as total, COUNT(*) as count')
            ->groupBy('month')
            ->get();

        return response()->json($stats);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/my-stats/favorite-products
    // ─────────────────────────────────────────────────────────────
    public function favoriteProducts(Request $request): JsonResponse
    {
        $products = $request->user()
            ->orders()
            ->where('payment_status', 'paid')
            ->with('items.product.primaryImage')
            ->get()
            ->flatMap(fn($o) => $o->items)
            ->groupBy('product_id')
            ->map(fn($g) => [
                'product'        => $g->first()->product,
                'total_quantity' => $g->sum('quantity'),
            ])
            ->sortByDesc('total_quantity')
            ->take(10)
            ->values();

        return response()->json($products);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/my-stats/consumption
    // ─────────────────────────────────────────────────────────────
    public function consumptionReport(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'total_spent'    => $user->orders()->where('payment_status', 'paid')->sum('total'),
            'total_orders'   => $user->orders()->where('payment_status', 'paid')->count(),
            'loyalty_points' => $user->loyalty_points,
            'badges_count'   => $user->badges()->count(),
            'local_products' => $user->orders()
                ->where('payment_status', 'paid')
                ->with('items.product')
                ->get()
                ->flatMap(fn($o) => $o->items)
                ->filter(fn($i) => $i->product?->is_local)
                ->count(),
        ]);
    }
}