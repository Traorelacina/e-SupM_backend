<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function index(Request $request): JsonResponse
    {
        $subs = $request->user()->subscriptions()
            ->with(['items.product.primaryImage', 'address'])
            ->latest()
            ->get();
        return response()->json($subs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'                    => ['nullable', 'string', 'max:100'],
            'type'                    => ['required', 'in:standard,custom'],
            'preset_type'             => ['nullable', 'string', 'in:foyer,famille,bio,entretien'],
            'frequency'               => ['required', 'in:weekly,biweekly,monthly'],
            'delivery_day'            => ['nullable', 'integer', 'between:1,7'],
            'delivery_week_of_month'  => ['nullable', 'integer', 'between:1,4'],
            'delivery_type'           => ['required', 'in:home,click_collect,locker'],
            'address_id'              => ['nullable', 'exists:addresses,id'],
            'payment_method'          => ['required', 'in:auto,manual'],
            'items'                   => ['required_if:type,custom', 'array', 'min:1'],
            'items.*.product_id'      => ['required_if:type,custom', 'exists:products,id'],
            'items.*.quantity'        => ['required_if:type,custom', 'integer', 'min:1'],
        ]);

        // Check user doesn't already have an active subscription
        $existing = $request->user()->subscriptions()->where('status', 'active')->count();
        if ($existing >= 3) {
            return response()->json(['message' => 'Vous avez déjà atteint le maximum de 3 abonnements actifs.'], 422);
        }

        return DB::transaction(function () use ($request) {
            $subscription = Subscription::create([
                'user_id'                => $request->user()->id,
                'name'                   => $request->name ?? 'Mon panier essentiel',
                'type'                   => $request->type,
                'preset_type'            => $request->preset_type,
                'frequency'              => $request->frequency,
                'delivery_day'           => $request->delivery_day,
                'delivery_week_of_month' => $request->delivery_week_of_month,
                'delivery_type'          => $request->delivery_type,
                'address_id'             => $request->address_id,
                'payment_method'         => $request->payment_method,
                'status'                 => 'active',
                'discount_percent'       => 5,
                'next_delivery_at'       => now()->addWeek(),
            ]);

            if ($request->type === 'custom' && $request->items) {
                $subtotal = 0;
                foreach ($request->items as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);
                    SubscriptionItem::create([
                        'subscription_id' => $subscription->id,
                        'product_id'      => $product->id,
                        'quantity'        => $itemData['quantity'],
                        'price'           => $product->price,
                    ]);
                    $subtotal += $product->price * $itemData['quantity'];
                }
                $total = $subtotal * (1 - $subscription->discount_percent / 100);
                $subscription->update(['subtotal' => $subtotal, 'total' => $total, 'next_delivery_at' => $subscription->computeNextDelivery()]);
            } elseif ($request->type === 'standard') {
                $this->loadPresetItems($subscription, $request->preset_type ?? 'foyer');
            }

            // Award subscription badge check
            $this->loyaltyService->checkAndAwardBadges($request->user());

            return response()->json([
                'message'      => 'Abonnement créé avec succès !',
                'subscription' => $subscription->load(['items.product.primaryImage', 'address']),
            ], 201);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $sub = $request->user()->subscriptions()->with(['items.product.primaryImage', 'address', 'orders' => fn($q) => $q->latest()->take(5)])->findOrFail($id);
        return response()->json($sub);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'frequency'     => ['nullable', 'in:weekly,biweekly,monthly'],
            'delivery_type' => ['nullable', 'in:home,click_collect,locker'],
            'address_id'    => ['nullable', 'exists:addresses,id'],
            'items'         => ['nullable', 'array'],
            'items.*.product_id' => ['exists:products,id'],
            'items.*.quantity'   => ['integer', 'min:1'],
        ]);

        $sub = $request->user()->subscriptions()->findOrFail($id);

        DB::transaction(function () use ($request, $sub) {
            $sub->update($request->only(['frequency', 'delivery_type', 'address_id', 'delivery_day']));

            if ($request->items) {
                $sub->items()->delete();
                $subtotal = 0;
                foreach ($request->items as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);
                    SubscriptionItem::create([
                        'subscription_id' => $sub->id,
                        'product_id'      => $product->id,
                        'quantity'        => $itemData['quantity'],
                        'price'           => $product->price,
                    ]);
                    $subtotal += $product->price * $itemData['quantity'];
                }
                $total = $subtotal * (1 - $sub->discount_percent / 100);
                $sub->update(['subtotal' => $subtotal, 'total' => $total]);
            }
        });

        return response()->json(['message' => 'Abonnement mis à jour', 'subscription' => $sub->fresh()->load('items.product')]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $request->validate(['until' => ['nullable', 'date', 'after:today']]);
        $sub = $request->user()->subscriptions()->where('status', 'active')->findOrFail($id);
        $sub->update(['status' => 'suspended', 'suspended_until' => $request->until ?? now()->addMonth()]);
        return response()->json(['message' => 'Abonnement suspendu temporairement']);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $sub = $request->user()->subscriptions()->where('status', 'suspended')->findOrFail($id);
        $sub->update(['status' => 'active', 'suspended_until' => null, 'next_delivery_at' => $sub->computeNextDelivery()]);
        return response()->json(['message' => 'Abonnement réactivé']);
    }

    /**
     * Annuler l'abonnement (statut "cancelled", garde l'historique)
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $sub = $request->user()->subscriptions()->findOrFail($id);
        $sub->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $request->reason]);
        return response()->json(['message' => 'Abonnement annulé']);
    }

    /**
     * Supprimer définitivement l'abonnement (soft delete) – uniquement s'il n'a aucune commande.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $subscription = $request->user()->subscriptions()->findOrFail($id);

        // Vérifier qu'aucune commande n'est liée à cet abonnement
        if ($subscription->orders()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un abonnement qui contient déjà des commandes. Utilisez l\'annulation à la place.'
            ], 422);
        }

        DB::transaction(function () use ($subscription) {
            // Supprimer les lignes de l'abonnement (cascade manuelle)
            $subscription->items()->delete();
            // Soft delete de l'abonnement lui-même
            $subscription->delete();
        });

        return response()->json(['message' => 'Abonnement supprimé avec succès.']);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $sub = $request->user()->subscriptions()->findOrFail($id);
        $orders = $sub->orders()->with('items')->latest()->paginate(10);
        return response()->json($orders);
    }

    private function loadPresetItems(Subscription $subscription, string $presetType): void
    {
        // Preset baskets - in real app, these would be configurable from admin
        $presets = [
            'foyer'    => ['Riz 5kg', 'Huile végétale 2L', 'Pâtes', 'Savon', 'Papier toilette'],
            'famille'  => ['Céréales petit-déj', 'Lait 1L', 'Jus de fruit', 'Biscuits enfants'],
            'bio'      => ['Légumes bio', 'Fruits bio', 'Yaourt bio', 'Pain complet'],
            'entretien'=> ['Lessive', 'Liquide vaisselle', 'Nettoyant multi-surfaces', 'Éponges'],
        ];

        // In a real scenario, you'd map these to actual products
        $products = Product::active()->take(5)->get();
        $subtotal = 0;
        foreach ($products as $product) {
            SubscriptionItem::create([
                'subscription_id' => $subscription->id,
                'product_id'      => $product->id,
                'quantity'        => 1,
                'price'           => $product->price,
            ]);
            $subtotal += $product->price;
        }
        $total = $subtotal * (1 - $subscription->discount_percent / 100);
        $subscription->update(['subtotal' => $subtotal, 'total' => $total, 'next_delivery_at' => $subscription->computeNextDelivery()]);
    }
}