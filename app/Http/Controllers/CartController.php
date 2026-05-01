<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Models\Cart as CartModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    // ════════════════════════════════════════════════════════════════════════
    // GET /api/cart
    // ════════════════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        try {
            $cart    = $this->cartService->getCart($request);
            $cart    = $this->loadAndFormatCart($cart);
            $summary = $this->cartService->getCartSummary($cart);

            return response()->json([
                'cart'    => $cart,
                'summary' => $this->formatSummary($summary, $cart),
            ]);
        } catch (\Exception $e) {
            Log::error('CartController::index – ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erreur lors du chargement du panier.',
                'cart'    => ['items' => []],
                'summary' => $this->emptySummary(),
            ], 200); // 200 pour ne pas déclencher un retry agressif côté front
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /api/cart/add
    // ════════════════════════════════════════════════════════════════════════

    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:100'],
            'size'       => ['nullable', 'string', 'max:50'],
            'color'      => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $cart = $this->cartService->getCart($request);

            $this->cartService->addItem(
                $cart,
                (int) $request->product_id,
                (int) $request->quantity,
                $request->size,
                $request->color
            );

            $cart    = $this->loadAndFormatCart($cart);
            $summary = $this->cartService->getCartSummary($cart);

            return response()->json([
                'message' => 'Produit ajouté au panier.',
                'cart'    => $cart,
                'summary' => $this->formatSummary($summary, $cart),
            ], 201);
        } catch (\Exception $e) {
            Log::error('CartController::add – ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // PUT /api/cart/item/{id}
    // ════════════════════════════════════════════════════════════════════════

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            $cart = $this->cartService->getCart($request);

            // Vérifier d'abord l'existence pour renvoyer un état propre
            if (!$this->cartService->itemExists($cart, $id)) {
                $cart    = $this->loadAndFormatCart($cart);
                $summary = $this->cartService->getCartSummary($cart);

                return response()->json([
                    'message'          => 'Article non trouvé. Panier rafraîchi.',
                    'cart'             => $cart,
                    'summary'          => $this->formatSummary($summary, $cart),
                    'requires_refresh' => true,
                ], 200);
            }

            $this->cartService->updateItem($cart, $id, (int) $request->quantity);

            // Recharger le panier après la mise à jour
            $cart    = $this->cartService->refreshCart($cart);
            $cart    = $this->loadAndFormatCart($cart);
            $summary = $this->cartService->getCartSummary($cart);

            return response()->json([
                'message' => 'Quantité mise à jour.',
                'cart'    => $cart,
                'summary' => $this->formatSummary($summary, $cart),
            ]);
        } catch (\Exception $e) {
            Log::error('CartController::updateItem – ' . $e->getMessage());

            // Tenter de renvoyer l'état actuel du panier même en cas d'erreur
            try {
                $cart    = $this->cartService->getCart($request);
                $cart    = $this->loadAndFormatCart($cart);
                $summary = $this->cartService->getCartSummary($cart);

                return response()->json([
                    'message'          => $e->getMessage(),
                    'cart'             => $cart,
                    'summary'          => $this->formatSummary($summary, $cart),
                    'requires_refresh' => true,
                ], 422);
            } catch (\Exception $e2) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // DELETE /api/cart/item/{id}
    // ════════════════════════════════════════════════════════════════════════

    public function removeItem(Request $request, int $id): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart($request);

            // Déjà supprimé → renvoyer l'état actuel proprement
            if (!$this->cartService->itemExists($cart, $id)) {
                $cart    = $this->loadAndFormatCart($cart);
                $summary = $this->cartService->getCartSummary($cart);

                return response()->json([
                    'message' => 'Article déjà supprimé.',
                    'cart'    => $cart,
                    'summary' => $this->formatSummary($summary, $cart),
                ], 200);
            }

            $this->cartService->removeItem($cart, $id);

            // Recharger après suppression
            $cart    = $this->cartService->refreshCart($cart);
            $cart    = $this->loadAndFormatCart($cart);
            $summary = $this->cartService->getCartSummary($cart);

            return response()->json([
                'message' => 'Produit retiré du panier.',
                'cart'    => $cart,
                'summary' => $this->formatSummary($summary, $cart),
            ]);
        } catch (\Exception $e) {
            Log::error('CartController::removeItem – ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // DELETE /api/cart
    // ════════════════════════════════════════════════════════════════════════

    public function clear(Request $request): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart($request);
            $this->cartService->clearCart($cart);

            return response()->json(['message' => 'Panier vidé.']);
        } catch (\Exception $e) {
            Log::error('CartController::clear – ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // POST /api/cart/coupon/apply
    // ════════════════════════════════════════════════════════════════════════

    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']]);

        try {
            $cart   = $this->cartService->getCart($request);
            $userId = auth()->id() ?? 0;

            $this->cartService->applyCoupon($cart, $request->code, $userId);

            $cart    = $this->loadAndFormatCart($cart);
            $summary = $this->cartService->getCartSummary($cart);

            return response()->json([
                'message' => 'Code promo appliqué !',
                'cart'    => $cart,
                'summary' => $this->formatSummary($summary, $cart),
            ]);
        } catch (\Exception $e) {
            Log::error('CartController::applyCoupon – ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // DELETE /api/cart/coupon
    // ════════════════════════════════════════════════════════════════════════

    public function removeCoupon(Request $request): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart($request);
            $this->cartService->removeCoupon($cart);

            $cart    = $this->loadAndFormatCart($cart);
            $summary = $this->cartService->getCartSummary($cart);

            return response()->json([
                'message' => 'Code promo retiré.',
                'cart'    => $cart,
                'summary' => $this->formatSummary($summary, $cart),
            ]);
        } catch (\Exception $e) {
            Log::error('CartController::removeCoupon – ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Charge les relations nécessaires et enrichit les items
     * avec price, line_total et l'URL de l'image principale.
     */
    private function loadAndFormatCart(CartModel $cart): CartModel
    {
        $cart->load([
            'items.product' => fn($q) => $q->with([
                'primaryImage',
                'category:id,name,slug,color',
            ]),
        ]);

        $cart->items->each(function ($item) {
            // Normaliser le prix (s'assure qu'il est toujours un float)
            $unitPrice  = (float) ($item->price ?? $item->product?->price ?? 0);
            $item->price      = $unitPrice;
            $item->line_total = round($unitPrice * (int) $item->quantity, 2);

            // Ajouter l'URL de l'image principale directement sur le produit
            if ($item->product) {
                $primaryImage = $item->product->primaryImage;

                $item->product->append_primary_image_url = $primaryImage
                    ? asset('storage/' . $primaryImage->path)
                    : null;
            }
        });

        return $cart;
    }

    /**
     * Normalise le tableau résumé pour garantir tous les champs attendus par le front.
     */
    private function formatSummary(mixed $summary, CartModel $cart): array
    {
        $data = is_array($summary) ? $summary : (array) $summary;

        return [
            'items_count'     => (int)   ($data['items_count']     ?? $cart->items->sum('quantity')),
            'subtotal'        => (float) ($data['subtotal']         ?? 0),
            'total'           => (float) ($data['total']            ?? 0),
            'delivery_fee'    => (float) ($data['delivery_fee']     ?? 0),
            'coupon_code'     =>          $data['coupon_code']      ?? null,
            'coupon_discount' => (float) ($data['coupon_discount']  ?? 0),
        ];
    }

    /**
     * Résumé vide pour les cas d'erreur.
     */
    private function emptySummary(): array
    {
        return [
            'items_count'     => 0,
            'subtotal'        => 0,
            'total'           => 0,
            'delivery_fee'    => 0,
            'coupon_code'     => null,
            'coupon_discount' => 0,
        ];
    }
}