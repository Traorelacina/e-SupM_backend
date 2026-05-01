<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CartService
{
    // ════════════════════════════════════════════════════════════════════════
    // RÉCUPÉRATION / CRÉATION DU PANIER
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Retourne le panier actif pour l'utilisateur connecté ou le guest.
     * Fusionne automatiquement le panier guest lors d'une connexion.
     */
    public function getCart(Request $request): Cart
    {
        if (Auth::check()) {
            // Panier utilisateur connecté
            $cart = Cart::firstOrCreate(
                ['user_id' => Auth::id()],
                [
                    'session_id' => null,
                    'expires_at' => now()->addDays(30),
                ]
            );

            // Fusionner le panier guest s'il existe
            $sessionCart = $this->getSessionCart($request);
            if ($sessionCart && $sessionCart->id !== $cart->id) {
                $this->mergeCart($sessionCart, $cart);
                $sessionCart->delete();
            }
        } else {
            $cart = $this->getOrCreateGuestCart($request);
        }

        // Toujours recharger les items avec leurs relations
        return $cart->load([
            'items.product.primaryImage',
            'items.product.category',
        ]);
    }

    /**
     * Récupère ou crée un panier guest via cookie.
     */
    private function getOrCreateGuestCart(Request $request): Cart
    {
        $sessionId = $request->cookie('cart_session');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            Cookie::queue('cart_session', $sessionId, 60 * 24 * 7); // 7 jours
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null],
            ['expires_at' => now()->addDays(7)]
        );
    }

    /**
     * Retrouve le panier guest en session (pour fusion lors de la connexion).
     */
    private function getSessionCart(Request $request): ?Cart
    {
        $sessionId = $request->cookie('cart_session');
        if (!$sessionId) return null;

        return Cart::where('session_id', $sessionId)
                   ->whereNull('user_id')
                   ->first();
    }

    /**
     * Fusionne les items du panier source dans le panier cible.
     * Si un item existe déjà (même produit + taille + couleur), on incrémente la quantité.
     */
    private function mergeCart(Cart $source, Cart $target): void
    {
        // S'assurer que les items du source sont chargés
        $source->loadMissing('items');

        foreach ($source->items as $item) {
            $existing = $target->items()
                ->where('product_id', $item->product_id)
                ->where('size', $item->size)
                ->where('color', $item->color)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $item->quantity);
            } else {
                $target->items()->create([
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $item->price,
                    'size'       => $item->size,
                    'color'      => $item->color,
                ]);
            }
        }

        Log::info('Carts merged', [
            'source_id' => $source->id,
            'target_id' => $target->id,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // GESTION DES ITEMS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Ajoute un produit au panier.
     */
    public function addItem(
        Cart $cart,
        int $productId,
        int $quantity,
        ?string $size = null,
        ?string $color = null
    ): CartItem {
        /** @var Product $product */
        $product = Product::where('is_active', true)->findOrFail($productId);

        // Vérification du stock
        if ($product->track_stock && $product->stock < $quantity) {
            throw new \Exception("Stock insuffisant. Stock disponible : {$product->stock}");
        }

        // Item existant avec mêmes options ?
        $existing = $cart->items()
            ->where('product_id', $productId)
            ->where('size', $size)
            ->where('color', $color)
            ->first();

        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            if ($product->track_stock && $product->stock < $newQty) {
                throw new \Exception("Stock insuffisant pour cette quantité.");
            }
            $existing->update(['quantity' => $newQty]);
            return $existing->refresh();
        }

        return $cart->items()->create([
            'product_id' => $productId,
            'quantity'   => $quantity,
            'price'      => $product->price,
            'size'       => $size,
            'color'      => $color,
        ]);
    }

    /**
     * Met à jour la quantité d'un item.
     * Retourne null si la quantité est 0 (item supprimé).
     */
    public function updateItem(Cart $cart, int $itemId, int $quantity): ?CartItem
    {
        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            Log::warning('CartService::updateItem – item not found', [
                'cart_id'    => $cart->id,
                'item_id'    => $itemId,
                'user_id'    => Auth::id(),
                'session_id' => $cart->session_id,
            ]);
            throw new \Exception("L'article n'existe plus dans votre panier.", 404);
        }

        if ($quantity <= 0) {
            $item->delete();
            return null;
        }

        $product = $item->product;
        if ($product && $product->track_stock && $product->stock < $quantity) {
            throw new \Exception("Stock insuffisant. Stock disponible : {$product->stock}");
        }

        $item->update(['quantity' => $quantity]);

        Log::info('Cart item updated', [
            'item_id'      => $itemId,
            'cart_id'      => $cart->id,
            'new_quantity' => $quantity,
        ]);

        return $item->refresh();
    }

    /**
     * Supprime un item du panier.
     */
    public function removeItem(Cart $cart, int $itemId): void
    {
        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            Log::warning('CartService::removeItem – item not found', [
                'cart_id'    => $cart->id,
                'item_id'    => $itemId,
                'user_id'    => Auth::id(),
                'session_id' => $cart->session_id,
            ]);
            throw new \Exception("L'article n'existe plus dans votre panier.", 404);
        }

        $item->delete();

        Log::info('Cart item removed', [
            'item_id' => $itemId,
            'cart_id' => $cart->id,
        ]);
    }

    /**
     * Vide entièrement le panier et retire le coupon.
     */
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);

        Log::info('Cart cleared', ['cart_id' => $cart->id]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // COUPONS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Applique un code promo au panier.
     */
    public function applyCoupon(Cart $cart, string $code, int $userId): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (!$coupon || !$coupon->isValid()) {
            throw new \Exception('Code promo invalide ou expiré.');
        }

        // Limite par utilisateur
        if ($userId > 0) {
            $userUsages = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsages >= $coupon->max_uses_per_user) {
                throw new \Exception('Vous avez déjà utilisé ce code promo.');
            }

            // Réservé première commande
            if ($coupon->is_first_order_only) {
                $hasOrders = \App\Models\Order::where('user_id', $userId)
                    ->where('payment_status', 'paid')
                    ->exists();

                if ($hasOrders) {
                    throw new \Exception('Ce code promo est réservé aux nouvelles commandes.');
                }
            }
        }

        $cart->loadMissing('items');
        $subtotal = $cart->subtotal;
        $discount = $coupon->calculateDiscount($subtotal);

        if ($discount <= 0) {
            throw new \Exception("Montant minimum requis : {$coupon->min_purchase} FCFA");
        }

        $cart->update(['coupon_code' => $coupon->code, 'coupon_discount' => $discount]);

        return [
            'coupon'   => $coupon,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'total'    => $subtotal - $discount,
        ];
    }

    /**
     * Retire le coupon du panier.
     */
    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // RÉSUMÉ & FRAIS DE LIVRAISON
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Retourne le résumé complet du panier (calculs côté serveur).
     */
    public function getCartSummary(Cart $cart): array
    {
        // S'assurer que les items et produits sont chargés
        $cart->loadMissing('items.product');

        $subtotal       = (float) $cart->subtotal;
        $couponDiscount = (float) ($cart->coupon_discount ?? 0);
        $deliveryFee    = $this->calculateDeliveryFee($subtotal);
        $total          = max(0, $subtotal - $couponDiscount + $deliveryFee);

        return [
            'items_count'     => (int) $cart->total_items,
            'subtotal'        => round($subtotal, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'delivery_fee'    => round($deliveryFee, 2),
            'total'           => round($total, 2),
            'coupon_code'     => $cart->coupon_code,
        ];
    }

    /**
     * Calcule les frais de livraison selon le sous-total.
     * Livraison gratuite dès 50 000 FCFA.
     */
    private function calculateDeliveryFee(float $subtotal): float
    {
        if ($subtotal >= 50000) return 0.0;
        if ($subtotal >= 20000) return 1000.0;
        return 2000.0;
    }

    // ════════════════════════════════════════════════════════════════════════
    // UTILITAIRES
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Vérifie si un item existe dans le panier.
     */
    public function itemExists(Cart $cart, int $itemId): bool
    {
        return $cart->items()->where('id', $itemId)->exists();
    }

    /**
     * Retourne un item par son ID (ou null).
     */
    public function getCartItem(Cart $cart, int $itemId): ?CartItem
    {
        return $cart->items()->where('id', $itemId)->first();
    }

    /**
     * Recharge le panier depuis la base de données.
     */
    public function refreshCart(Cart $cart): Cart
    {
        return $cart->fresh()->load([
            'items.product.primaryImage',
            'items.product.category',
        ]);
    }
}