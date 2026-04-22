<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Get or create the cart for current session/user
     */
    public function getCart(Request $request): Cart
    {
        if (Auth::check()) {
            $cart = Cart::firstOrCreate(
                ['user_id' => Auth::id()],
                ['expires_at' => now()->addDays(30)]
            );

            // Merge guest cart if exists
            $sessionCart = $this->getSessionCart($request);
            if ($sessionCart && $sessionCart->id !== $cart->id) {
                $this->mergeCart($sessionCart, $cart);
                $sessionCart->delete();
            }
        } else {
            $cart = $this->getOrCreateGuestCart($request);
        }

        return $cart->load(['items.product.primaryImage', 'items.product.category']);
    }

    private function getOrCreateGuestCart(Request $request): Cart
    {
        $sessionId = $request->cookie('cart_session');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            Cookie::queue('cart_session', $sessionId, 60 * 24 * 7);
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['expires_at' => now()->addDays(7)]
        );
    }

    private function getSessionCart(Request $request): ?Cart
    {
        $sessionId = $request->cookie('cart_session');
        if (!$sessionId) {
            return null;
        }

        return Cart::where('session_id', $sessionId)->whereNull('user_id')->first();
    }

    private function mergeCart(Cart $source, Cart $target): void
    {
        foreach ($source->items as $item) {
            $existing = $target->items()->where('product_id', $item->product_id)->first();
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
    }

    /**
     * Add product to cart
     */
    public function addItem(Cart $cart, int $productId, int $quantity, ?string $size = null, ?string $color = null): CartItem
    {
        $product = Product::active()->findOrFail($productId);

        if ($product->track_stock && $product->stock < $quantity) {
            throw new \Exception("Stock insuffisant. Stock disponible: {$product->stock}");
        }

        $existing = $cart->items()->where('product_id', $productId)->first();

        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            if ($product->track_stock && $product->stock < $newQty) {
                throw new \Exception("Stock insuffisant pour cette quantité.");
            }
            $existing->update(['quantity' => $newQty]);
            return $existing;
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
     * Update item quantity
     */
    public function updateItem(Cart $cart, int $itemId, int $quantity): CartItem
    {
        $item = $cart->items()->findOrFail($itemId);
        $product = $item->product;

        if ($product->track_stock && $product->stock < $quantity) {
            throw new \Exception("Stock insuffisant.");
        }

        $item->update(['quantity' => $quantity]);
        return $item->refresh();
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Cart $cart, int $itemId): void
    {
        $cart->items()->findOrFail($itemId)->delete();
    }

    /**
     * Clear all cart items
     */
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);
    }

    /**
     * Apply coupon to cart
     */
    public function applyCoupon(Cart $cart, string $code, int $userId): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (!$coupon || !$coupon->isValid()) {
            throw new \Exception('Code promo invalide ou expiré.');
        }

        // Check per user limit
        $userUsages = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
            ->where('user_id', $userId)->count();

        if ($userUsages >= $coupon->max_uses_per_user) {
            throw new \Exception('Vous avez déjà utilisé ce code promo.');
        }

        // Check first order only
        if ($coupon->is_first_order_only) {
            $hasOrders = \App\Models\Order::where('user_id', $userId)->where('payment_status', 'paid')->exists();
            if ($hasOrders) {
                throw new \Exception('Ce code promo est réservé aux nouvelles commandes.');
            }
        }

        $cart->load('items');
        $subtotal = $cart->subtotal;
        $discount = $coupon->calculateDiscount($subtotal);

        if ($discount <= 0) {
            throw new \Exception("Montant minimum requis: {$coupon->min_purchase} FCFA");
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
     * Remove coupon from cart
     */
    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(Cart $cart): array
    {
        $cart->load('items.product');
        $subtotal = $cart->subtotal;
        $couponDiscount = $cart->coupon_discount ?? 0;
        $deliveryFee = $this->calculateDeliveryFee($subtotal);
        $total = $subtotal - $couponDiscount + $deliveryFee;

        return [
            'items_count'      => $cart->total_items,
            'subtotal'         => $subtotal,
            'coupon_discount'  => $couponDiscount,
            'delivery_fee'     => $deliveryFee,
            'total'            => max(0, $total),
            'coupon_code'      => $cart->coupon_code,
        ];
    }

    private function calculateDeliveryFee(float $subtotal): float
    {
        if ($subtotal >= 50000) return 0; // livraison gratuite
        if ($subtotal >= 20000) return 1000;
        return 2000;
    }
}
