<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SelectiveSubscription;
use App\Models\SelectiveSubscriptionItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Interface admin : gestion complète des abonnements sélectifs utilisateurs.
 */
class AdminSelectiveSubscriptionController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    // ─────────────────────────────────────────────────────────────
    // Liste paginée avec filtres
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = SelectiveSubscription::with([
            'user:id,name,email,phone',
            'items' => fn($q) => $q->with('product:id,name,price')->orderBy('sort_order'),
            'address',
        ]);

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('delivery_type')) {
            $query->where('delivery_type', $request->delivery_type);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', $search)->orWhere('email', 'like', $search)
            );
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $subs    = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $subs->map(fn($sub) => $this->formatForAdmin($sub)),
            'meta'    => [
                'current_page' => $subs->currentPage(),
                'last_page'    => $subs->lastPage(),
                'per_page'     => $subs->perPage(),
                'total'        => $subs->total(),
            ],
            'stats'   => $this->getStats(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Détail complet d'un abonnement
    // ─────────────────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $sub = SelectiveSubscription::with([
            'user',
            'items'           => fn($q) => $q->with(['product.primaryImage', 'product.category:id,name,color'])->orderBy('sort_order'),
            'address',
            'orders'          => fn($q) => $q->latest()->take(10),
            'orders.items',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatForAdmin($sub, detailed: true),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Modifier statut, fréquence, prochaine livraison
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'            => ['nullable', 'in:active,suspended,cancelled'],
            'frequency'         => ['nullable', 'in:weekly,biweekly,monthly'],
            'next_delivery_at'  => ['nullable', 'date', 'after:today'],
            'payment_method'    => ['nullable', 'in:auto,manual'],
            'discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:50'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $sub = SelectiveSubscription::findOrFail($id);
        $sub->update($request->only([
            'status', 'frequency', 'next_delivery_at',
            'payment_method', 'discount_percent', 'notes',
        ]));

        // Recalcul si remise modifiée
        if ($request->has('discount_percent')) {
            $total = round($sub->subtotal * (1 - $sub->discount_percent / 100), 2);
            $sub->update(['total' => $total]);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Abonnement mis à jour.',
            'subscription' => $this->formatForAdmin($sub->fresh()->load(['user:id,name,email', 'items.product'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Suspendre depuis l'admin
    // ─────────────────────────────────────────────────────────────
    public function suspend(int $id): JsonResponse
    {
        $sub = SelectiveSubscription::where('status', 'active')->findOrFail($id);
        $sub->update([
            'status'          => 'suspended',
            'suspended_until' => now()->addMonth(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement suspendu par l\'administrateur.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Réactiver depuis l'admin
    // ─────────────────────────────────────────────────────────────
    public function resume(int $id): JsonResponse
    {
        $sub = SelectiveSubscription::where('status', 'suspended')->findOrFail($id);
        $sub->update([
            'status'           => 'active',
            'suspended_until'  => null,
            'next_delivery_at' => $sub->computeNextDelivery(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement réactivé.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Déclencher manuellement une commande pour un abonnement
    // ─────────────────────────────────────────────────────────────
    public function processManually(int $id): JsonResponse
    {
        $sub = SelectiveSubscription::with([
            'items' => fn($q) => $q->where('is_active', true)->with('product'),
            'user',
        ])->findOrFail($id);

        if ($sub->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les abonnements actifs peuvent être traités.',
            ], 422);
        }

        $activeItems = $sub->items->filter(fn($i) => $i->product && $i->product->in_stock);
        if ($activeItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun article actif et disponible dans cet abonnement.',
            ], 422);
        }

        try {
            $order = $this->orderService->createSelectiveSubscriptionOrder($sub);

            $sub->update(['next_delivery_at' => $sub->computeNextDelivery()]);

            return response()->json([
                'success' => true,
                'message' => 'Commande générée avec succès.',
                'order'   => [
                    'id'         => $order->id,
                    'reference'  => $order->reference ?? $order->id,
                    'total'      => $order->total,
                    'created_at' => $order->created_at->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Abonnements dont la livraison approche (dans les 3 jours)
    // ─────────────────────────────────────────────────────────────
    public function upcoming(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->get('days', 3), 14));

        $subs = SelectiveSubscription::where('status', 'active')
            ->where('next_delivery_at', '<=', now()->addDays($days))
            ->with([
                'user:id,name,email,phone',
                'items' => fn($q) => $q->where('is_active', true)->with('product:id,name,stock'),
            ])
            ->orderBy('next_delivery_at')
            ->get()
            ->map(fn($sub) => [
                'id'               => $sub->id,
                'name'             => $sub->name,
                'next_delivery_at' => $sub->next_delivery_at?->toISOString(),
                'delivery_type'    => $sub->delivery_type,
                'payment_method'   => $sub->payment_method,
                'total'            => (float) $sub->total,
                'active_items'     => $sub->items->count(),
                'user'             => [
                    'id'    => $sub->user->id,
                    'name'  => $sub->user->name,
                    'email' => $sub->user->email,
                    'phone' => $sub->user->phone,
                ],
                'has_stock_issue'  => $sub->items->contains(fn($i) => $i->product && $i->product->stock <= 0),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $subs,
            'meta'    => ['days_ahead' => $days, 'total' => $subs->count()],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Statistiques globales
    // ─────────────────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->getStats(detailed: true),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────
    private function getStats(bool $detailed = false): array
    {
        $base = [
            'total'     => SelectiveSubscription::count(),
            'active'    => SelectiveSubscription::where('status', 'active')->count(),
            'suspended' => SelectiveSubscription::where('status', 'suspended')->count(),
            'cancelled' => SelectiveSubscription::where('status', 'cancelled')->count(),
        ];

        if ($detailed) {
            $base['by_frequency'] = SelectiveSubscription::where('status', 'active')
                ->selectRaw('frequency, COUNT(*) as count')
                ->groupBy('frequency')
                ->pluck('count', 'frequency');

            $base['by_payment'] = SelectiveSubscription::where('status', 'active')
                ->selectRaw('payment_method, COUNT(*) as count')
                ->groupBy('payment_method')
                ->pluck('count', 'payment_method');

            $base['revenue_monthly'] = SelectiveSubscription::where('status', 'active')
                ->sum('total');

            $base['upcoming_3days'] = SelectiveSubscription::where('status', 'active')
                ->where('next_delivery_at', '<=', now()->addDays(3))
                ->count();
        }

        return $base;
    }

    private function formatForAdmin(SelectiveSubscription $sub, bool $detailed = false): array
    {
        $activeItems = $sub->items->where('is_active', true);

        $data = [
            'id'               => $sub->id,
            'name'             => $sub->name,
            'status'           => $sub->status,
            'frequency'        => $sub->frequency,
            'delivery_type'    => $sub->delivery_type,
            'payment_method'   => $sub->payment_method,
            'discount_percent' => (float) $sub->discount_percent,
            'subtotal'         => (float) $sub->subtotal,
            'total'            => (float) $sub->total,
            'next_delivery_at' => $sub->next_delivery_at?->toISOString(),
            'suspended_until'  => $sub->suspended_until?->toISOString(),
            'cancelled_at'     => $sub->cancelled_at?->toISOString(),
            'cancel_reason'    => $sub->cancel_reason,
            'notes'            => $sub->notes ?? null,
            'items_total'      => $sub->items->count(),
            'active_items'     => $activeItems->count(),
            'created_at'       => $sub->created_at?->toISOString(),
            'updated_at'       => $sub->updated_at?->toISOString(),
            'user'             => $sub->user ? [
                'id'    => $sub->user->id,
                'name'  => $sub->user->name,
                'email' => $sub->user->email,
                'phone' => $sub->user->phone ?? null,
            ] : null,
            'address' => $sub->address ? [
                'id'         => $sub->address->id,
                'full_label' => $sub->address->full_label ?? $sub->address->address,
            ] : null,
        ];

        if ($detailed) {
            $data['items'] = $sub->items->map(fn($item) => [
                'id'          => $item->id,
                'quantity'    => $item->quantity,
                'price'       => (float) $item->price,
                'is_active'   => (bool) $item->is_active,
                'sort_order'  => $item->sort_order,
                'line_total'  => $item->is_active ? round($item->price * $item->quantity, 2) : 0,
                'product'     => $item->product ? [
                    'id'                => $item->product->id,
                    'name'              => $item->product->name,
                    'price'             => (float) $item->product->price,
                    'in_stock'          => ($item->product->stock ?? 0) > 0,
                    'primary_image_url' => $item->product->primaryImage
                        ? asset('storage/' . $item->product->primaryImage->path)
                        : null,
                    'category'          => $item->product->category
                        ? ['id' => $item->product->category->id, 'name' => $item->product->category->name]
                        : null,
                ] : null,
            ])->values();

            $data['recent_orders'] = $sub->orders ? $sub->orders->map(fn($o) => [
                'id'         => $o->id,
                'total'      => (float) $o->total,
                'status'     => $o->status,
                'created_at' => $o->created_at?->toISOString(),
            ]) : [];
        }

        return $data;
    }
}