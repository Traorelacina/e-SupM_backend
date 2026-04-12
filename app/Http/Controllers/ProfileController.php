<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['defaultAddress', 'badges']));
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:20', 'unique:users,phone,' . $request->user()->id],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender'        => ['nullable', 'in:male,female,other'],
            'language'      => ['nullable', 'in:fr,en'],
            'notification_preferences' => ['nullable', 'array'],
        ]);
        $request->user()->update($request->only(['name','phone','date_of_birth','gender','language','notification_preferences']));
        return response()->json(['message' => 'Profil mis à jour', 'user' => $request->user()->fresh()]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);
        $path = $request->file('avatar')->store('avatars', 'public');
        $request->user()->update(['avatar' => $path]);
        return response()->json(['message' => 'Photo de profil mise à jour', 'avatar' => asset('storage/' . $path)]);
    }

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
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
        return response()->json(['message' => 'Mot de passe modifié avec succès']);
    }

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

    public function monthlyStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $user->orders()->where('payment_status','paid')->whereYear('created_at', now()->year)->selectRaw('MONTH(created_at) as month, SUM(total) as total, COUNT(*) as count')->groupBy('month')->get();
        return response()->json($stats);
    }

    public function favoriteProducts(Request $request): JsonResponse
    {
        $products = $request->user()->orders()->where('payment_status','paid')->with('items.product.primaryImage')
            ->get()->flatMap(fn($o) => $o->items)->groupBy('product_id')
            ->map(fn($g) => ['product' => $g->first()->product, 'total_quantity' => $g->sum('quantity')])
            ->sortByDesc('total_quantity')->take(10)->values();
        return response()->json($products);
    }

    public function consumptionReport(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'total_spent'       => $user->orders()->where('payment_status','paid')->sum('total'),
            'total_orders'      => $user->orders()->where('payment_status','paid')->count(),
            'loyalty_points'    => $user->loyalty_points,
            'badges_count'      => $user->badges()->count(),
            'local_products'    => $user->orders()->where('payment_status','paid')->with('items.product')->get()->flatMap(fn($o) => $o->items)->filter(fn($i) => $i->product?->is_local)->count(),
        ]);
    }
}
