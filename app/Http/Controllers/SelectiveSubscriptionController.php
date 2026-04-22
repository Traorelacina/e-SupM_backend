<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SelectiveSubscription;
use App\Models\SelectiveSubscriptionItem;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Abonnement sélectif : l'utilisateur compose son panier depuis le catalogue.
 * Chaque article peut être activé/désactivé indépendamment.
 * Paiement auto ou manuel, fréquence configurable par client.
 */
class SelectiveSubscriptionController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    // ─────────────────────────────────────────────────────────────
    // Liste des abonnements sélectifs de l'utilisateur connecté
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $request->user()
            ->selectiveSubscriptions()
            ->with([
                'items' => fn($q) => $q->orderBy('sort_order'),
                'items.product.primaryImage',
                'items.product.category:id,name,color',
                'address',
            ])
            ->latest()
            ->get()
            ->map(fn($sub) => $this->formatSubscription($sub));

        return response()->json([
            'success' => true,
            'data'    => $subscriptions,
            'meta'    => [
                'total'       => $subscriptions->count(),
                'active'      => $subscriptions->where('status', 'active')->count(),
                'max_allowed' => 3,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Créer un nouvel abonnement sélectif
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'                   => ['nullable', 'string', 'max:120'],
            'frequency'              => ['required', 'in:weekly,biweekly,monthly'],
            'delivery_day'           => ['nullable', 'integer', 'between:1,7'],
            'delivery_week_of_month' => ['nullable', 'integer', 'between:1,4'],
            'delivery_type'          => ['required', 'in:home,click_collect,locker'],
            'address_id'             => ['nullable', 'exists:addresses,id'],
            'payment_method'         => ['required', 'in:auto,manual'],
            'items'                  => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id'     => ['required', 'exists:products,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.is_active'      => ['boolean'],
        ]);

        // Limite de 3 abonnements actifs par utilisateur
        $activeCount = $request->user()
            ->selectiveSubscriptions()
            ->where('status', 'active')
            ->count();

        if ($activeCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum de 3 abonnements sélectifs actifs atteint.',
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $subscription = SelectiveSubscription::create([
                'user_id'                => $request->user()->id,
                'name'                   => $request->name ?? 'Mon panier sélectif',
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

            $subtotal   = 0;
            $sortOrder  = 0;

            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $isActive = $itemData['is_active'] ?? true;
                $qty      = (int) $itemData['quantity'];

                SelectiveSubscriptionItem::create([
                    'selective_subscription_id' => $subscription->id,
                    'product_id'                => $product->id,
                    'quantity'                  => $qty,
                    'price'                     => $product->price,
                    'is_active'                 => $isActive,
                    'sort_order'                => $sortOrder++,
                ]);

                if ($isActive) {
                    $subtotal += $product->price * $qty;
                }
            }

            $total = round($subtotal * (1 - $subscription->discount_percent / 100), 2);

            $subscription->update([
                'subtotal'          => $subtotal,
                'total'             => $total,
                'next_delivery_at'  => $subscription->computeNextDelivery(),
            ]);

            $this->loyaltyService->checkAndAwardBadges($request->user());

            return response()->json([
                'success'      => true,
                'message'      => 'Abonnement sélectif créé avec succès.',
                'subscription' => $this->formatSubscription(
                    $subscription->load(['items.product.primaryImage', 'address'])
                ),
            ], 201);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Détail d'un abonnement sélectif
    // ─────────────────────────────────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->with([
                'items'                 => fn($q) => $q->orderBy('sort_order'),
                'items.product.primaryImage',
                'items.product.category:id,name,color',
                'address',
                'orders'                => fn($q) => $q->latest()->take(5),
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatSubscription($subscription),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Mettre à jour un abonnement (fréquence, livraison, paiement)
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name'           => ['nullable', 'string', 'max:120'],
            'frequency'      => ['nullable', 'in:weekly,biweekly,monthly'],
            'delivery_type'  => ['nullable', 'in:home,click_collect,locker'],
            'address_id'     => ['nullable', 'exists:addresses,id'],
            'delivery_day'   => ['nullable', 'integer', 'between:1,7'],
            'payment_method' => ['nullable', 'in:auto,manual'],
        ]);

        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->findOrFail($id);

        $subscription->update(
            $request->only(['name', 'frequency', 'delivery_type', 'address_id', 'delivery_day', 'payment_method'])
        );

        if ($request->has('frequency')) {
            $subscription->update(['next_delivery_at' => $subscription->computeNextDelivery()]);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Abonnement mis à jour.',
            'subscription' => $this->formatSubscription(
                $subscription->fresh()->load(['items.product.primaryImage', 'address'])
            ),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Ajouter un produit à l'abonnement
    // ─────────────────────────────────────────────────────────────
    public function addItem(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:99'],
            'is_active'  => ['boolean'],
        ]);

        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->where('status', '!=', 'cancelled')
            ->findOrFail($id);

        // Vérifier si le produit est déjà dans l'abonnement
        $existing = $subscription->items()
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            $existing->update([
                'quantity'  => $existing->quantity + (int) $request->quantity,
                'is_active' => $request->boolean('is_active', $existing->is_active),
            ]);
            $item = $existing;
        } else {
            $product   = Product::findOrFail($request->product_id);
            $sortOrder = $subscription->items()->max('sort_order') + 1;

            $item = SelectiveSubscriptionItem::create([
                'selective_subscription_id' => $subscription->id,
                'product_id'                => $product->id,
                'quantity'                  => (int) $request->quantity,
                'price'                     => $product->price,
                'is_active'                 => $request->boolean('is_active', true),
                'sort_order'                => $sortOrder,
            ]);
        }

        $this->recalculateTotals($subscription);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté à l\'abonnement.',
            'item'    => $item->load('product.primaryImage'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Modifier la quantité ou l'état actif d'un article
    // ─────────────────────────────────────────────────────────────
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'quantity'  => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->findOrFail($id);

        $item = $subscription->items()->findOrFail($itemId);

        $updates = [];
        if ($request->has('quantity')) {
            $updates['quantity'] = (int) $request->quantity;
        }
        if ($request->has('is_active')) {
            $updates['is_active'] = $request->boolean('is_active');
        }

        $item->update($updates);
        $this->recalculateTotals($subscription);

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour.',
            'item'    => $item->fresh()->load('product.primaryImage'),
            'totals'  => [
                'subtotal' => $subscription->fresh()->subtotal,
                'total'    => $subscription->fresh()->total,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Activer / désactiver un article (toggle rapide)
    // ─────────────────────────────────────────────────────────────
    public function toggleItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->findOrFail($id);

        $item = $subscription->items()->findOrFail($itemId);
        $item->update(['is_active' => !$item->is_active]);

        $this->recalculateTotals($subscription);

        return response()->json([
            'success'   => true,
            'message'   => $item->is_active ? 'Article activé.' : 'Article désactivé.',
            'is_active' => $item->fresh()->is_active,
            'totals'    => [
                'subtotal' => $subscription->fresh()->subtotal,
                'total'    => $subscription->fresh()->total,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Supprimer un article de l'abonnement
    // ─────────────────────────────────────────────────────────────
    public function removeItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->findOrFail($id);

        $item = $subscription->items()->findOrFail($itemId);
        $item->delete();

        $this->recalculateTotals($subscription);

        return response()->json([
            'success' => true,
            'message' => 'Article supprimé de l\'abonnement.',
            'totals'  => [
                'subtotal' => $subscription->fresh()->subtotal,
                'total'    => $subscription->fresh()->total,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Remplacer tous les articles (synchronisation complète)
    // ─────────────────────────────────────────────────────────────
    public function syncItems(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items'              => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.is_active'  => ['boolean'],
        ]);

        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->where('status', '!=', 'cancelled')
            ->findOrFail($id);

        DB::transaction(function () use ($request, $subscription) {
            $subscription->items()->delete();
            $sortOrder = 0;

            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                SelectiveSubscriptionItem::create([
                    'selective_subscription_id' => $subscription->id,
                    'product_id'                => $product->id,
                    'quantity'                  => (int) $itemData['quantity'],
                    'price'                     => $product->price,
                    'is_active'                 => $itemData['is_active'] ?? true,
                    'sort_order'                => $sortOrder++,
                ]);
            }

            $this->recalculateTotals($subscription);
        });

        return response()->json([
            'success'      => true,
            'message'      => 'Panier sélectif synchronisé.',
            'subscription' => $this->formatSubscription(
                $subscription->fresh()->load(['items.product.primaryImage', 'address'])
            ),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Suspendre un abonnement
    // ─────────────────────────────────────────────────────────────
    public function suspend(Request $request, int $id): JsonResponse
    {
        $request->validate(['until' => ['nullable', 'date', 'after:today']]);

        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->where('status', 'active')
            ->findOrFail($id);

        $subscription->update([
            'status'          => 'suspended',
            'suspended_until' => $request->until ?? now()->addMonth(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement suspendu temporairement.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Réactiver un abonnement suspendu
    // ─────────────────────────────────────────────────────────────
    public function resume(Request $request, int $id): JsonResponse
    {
        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->where('status', 'suspended')
            ->findOrFail($id);

        $subscription->update([
            'status'          => 'active',
            'suspended_until' => null,
            'next_delivery_at' => $subscription->computeNextDelivery(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement réactivé avec succès.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Annuler un abonnement
    // ─────────────────────────────────────────────────────────────
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->findOrFail($id);

        $subscription->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement annulé.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Historique des commandes liées à l'abonnement
    // ─────────────────────────────────────────────────────────────
    public function history(Request $request, int $id): JsonResponse
    {
        $subscription = $request->user()
            ->selectiveSubscriptions()
            ->findOrFail($id);

        $orders = $subscription->orders()
            ->with('items')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────
    private function recalculateTotals(SelectiveSubscription $subscription): void
    {
        $subscription->refresh();
        $subtotal = $subscription->items()
            ->where('is_active', true)
            ->get()
            ->sum(fn($item) => $item->price * $item->quantity);

        $total = round($subtotal * (1 - $subscription->discount_percent / 100), 2);

        $subscription->update([
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);
    }

    private function formatSubscription(SelectiveSubscription $sub): array
    {
        $activeItems = $sub->items->where('is_active', true);

        return [
            'id'                     => $sub->id,
            'name'                   => $sub->name,
            'status'                 => $sub->status,
            'frequency'              => $sub->frequency,
            'delivery_day'           => $sub->delivery_day,
            'delivery_week_of_month' => $sub->delivery_week_of_month,
            'delivery_type'          => $sub->delivery_type,
            'payment_method'         => $sub->payment_method,
            'discount_percent'       => $sub->discount_percent,
            'subtotal'               => (float) $sub->subtotal,
            'total'                  => (float) $sub->total,
            'next_delivery_at'       => $sub->next_delivery_at?->toISOString(),
            'suspended_until'        => $sub->suspended_until?->toISOString(),
            'cancelled_at'           => $sub->cancelled_at?->toISOString(),
            'cancel_reason'          => $sub->cancel_reason,
            'items_count'            => $sub->items->count(),
            'active_items_count'     => $activeItems->count(),
            'address'                => $sub->address ? [
                'id'         => $sub->address->id,
                'full_label' => $sub->address->full_label ?? $sub->address->address,
            ] : null,
            'items'                  => $sub->items->map(fn($item) => [
                'id'          => $item->id,
                'product_id'  => $item->product_id,
                'quantity'    => $item->quantity,
                'price'       => (float) $item->price,
                'is_active'   => (bool) $item->is_active,
                'sort_order'  => $item->sort_order,
                'line_total'  => $item->is_active ? round($item->price * $item->quantity, 2) : 0,
                'product'     => $item->product ? [
                    'id'                => $item->product->id,
                    'name'              => $item->product->name,
                    'slug'              => $item->product->slug,
                    'price'             => (float) $item->product->price,
                    'in_stock'          => $item->product->stock > 0,
                    'primary_image_url' => $item->product->primaryImage
                        ? asset('storage/' . $item->product->primaryImage->path)
                        : null,
                    'category'          => $item->product->category
                        ? ['id' => $item->product->category->id, 'name' => $item->product->category->name]
                        : null,
                ] : null,
            ])->values(),
            'created_at'             => $sub->created_at?->toISOString(),
            'updated_at'             => $sub->updated_at?->toISOString(),
        ];
    }
}