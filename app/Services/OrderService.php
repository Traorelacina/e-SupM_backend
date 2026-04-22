<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\SelectiveSubscription;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private LoyaltyService $loyaltyService,
        private CartService $cartService
    ) {}

    /**
     * Create an order from the cart
     */
    public function createFromCart(User $user, Cart $cart, array $data): Order
    {
        $cart->load('items.product');

        if ($cart->items->isEmpty()) {
            throw new \Exception('Votre panier est vide.');
        }

        // Validate stock
        foreach ($cart->items as $item) {
            if ($item->product->track_stock && $item->product->stock < $item->quantity) {
                throw new \Exception("Stock insuffisant pour: {$item->product->name}");
            }
        }

        return DB::transaction(function () use ($user, $cart, $data) {
            $subtotal = $cart->subtotal;
            $couponDiscount = $cart->coupon_discount ?? 0;
            $deliveryFee = $this->calculateDeliveryFee($subtotal, $data['delivery_type'] ?? 'home');

            // Loyalty points to use
            $loyaltyDiscount = 0;
            if (!empty($data['use_loyalty_points'])) {
                $pointsToUse = min($data['use_loyalty_points'], $user->loyalty_points);
                $loyaltyDiscount = $pointsToUse / 100; // 100 points = 1 FCFA (adjust as needed)
            }

            $total = max(0, $subtotal - $couponDiscount - $loyaltyDiscount + $deliveryFee);

            $order = Order::create([
                'user_id'         => $user->id,
                'address_id'      => $data['address_id'] ?? null,
                'delivery_type'   => $data['delivery_type'] ?? 'home',
                'pickup_store'    => $data['pickup_store'] ?? null,
                'payment_method'  => $data['payment_method'] ?? 'mobile_money',
                'subtotal'        => $subtotal,
                'discount_amount' => $couponDiscount,
                'delivery_fee'    => $deliveryFee,
                'loyalty_discount'=> $loyaltyDiscount,
                'total'           => $total,
                'coupon_code'     => $cart->coupon_code,
                'notes'           => $data['notes'] ?? null,
                'loyalty_points_used' => $data['use_loyalty_points'] ?? 0,
            ]);

            // Create order items
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product->name,
                    'product_sku'   => $item->product->sku,
                    'product_image' => $item->product->primary_image_url,
                    'unit_price'    => $item->price,
                    'compare_price' => $item->product->compare_price,
                    'quantity'      => $item->quantity,
                    'total'         => $item->price * $item->quantity,
                    'size'          => $item->size,
                    'color'         => $item->color,
                ]);

                // Decrement stock
                $item->product->decrementStock($item->quantity);
            }

            // Handle coupon usage
            if ($cart->coupon_code) {
                $coupon = Coupon::where('code', $cart->coupon_code)->first();
                if ($coupon) {
                    $coupon->increment('used_count');
                    CouponUsage::create([
                        'coupon_id'        => $coupon->id,
                        'user_id'          => $user->id,
                        'order_id'         => $order->id,
                        'discount_applied' => $couponDiscount,
                    ]);
                }
            }

            // Deduct loyalty points used
            if ($loyaltyDiscount > 0 && !empty($data['use_loyalty_points'])) {
                $user->spendLoyaltyPoints($data['use_loyalty_points'], "Utilisation sur commande #{$order->order_number}");
            }

            // Clear cart
            $this->cartService->clearCart($cart);

            return $order->fresh()->load(['items', 'address']);
        });
    }

    /**
     * Mark order as paid and award loyalty points
     */
    public function markAsPaid(Order $order, string $transactionId, string $reference): void
    {
        DB::transaction(function () use ($order, $transactionId, $reference) {
            $order->update([
                'status'             => 'confirmed',
                'payment_status'     => 'paid',
                'transaction_id'     => $transactionId,
                'payment_reference'  => $reference,
                'paid_at'            => now(),
            ]);

            // Award loyalty points: 1 point per 100 FCFA
            $points = (int)($order->total / 100);
            if ($points > 0) {
                $order->update(['loyalty_points_earned' => $points]);
                $this->loyaltyService->awardPoints(
                    $order->user,
                    $points,
                    'earned',
                    "Points gagnés sur commande #{$order->order_number}",
                    $order->id
                );
            }

            // Notify user
            $order->user->notify(new \App\Notifications\OrderConfirmedNotification($order));

            // Check badges
            $this->loyaltyService->checkAndAwardBadges($order->user);
        });
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Order $order, string $reason = ''): void
    {
        if (!$order->isCancellable()) {
            throw new \Exception('Cette commande ne peut plus être annulée.');
        }

        DB::transaction(function () use ($order, $reason) {
            // Restore stock
            foreach ($order->items as $item) {
                if ($item->product && $item->product->track_stock) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            // Restore coupon usage
            if ($order->coupon_code) {
                $coupon = Coupon::where('code', $order->coupon_code)->first();
                $coupon?->decrement('used_count');
                CouponUsage::where('order_id', $order->id)->delete();
            }

            // Refund loyalty points used
            if ($order->loyalty_points_used > 0) {
                $this->loyaltyService->awardPoints(
                    $order->user,
                    $order->loyalty_points_used,
                    'bonus',
                    "Remboursement points annulation #{$order->order_number}"
                );
            }

            // Deduct points earned if already awarded
            if ($order->loyalty_points_earned > 0) {
                $order->user->spendLoyaltyPoints(
                    $order->loyalty_points_earned,
                    "Annulation commande #{$order->order_number}"
                );
            }

            $order->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason'=> $reason,
            ]);
        });
    }

    /**
     * Create subscription order (standard subscription)
     */
    public function createSubscriptionOrder(\App\Models\Subscription $subscription): Order
    {
        return DB::transaction(function () use ($subscription) {
            $subtotal = $subscription->items->sum(fn($i) => $i->price * $i->quantity);
            $discount = $subtotal * ($subscription->discount_percent / 100);
            $total = $subtotal - $discount;

            $order = Order::create([
                'user_id'               => $subscription->user_id,
                'address_id'            => $subscription->address_id,
                'delivery_type'         => $subscription->delivery_type,
                'status'                => 'confirmed',
                'payment_method'        => $subscription->payment_method,
                'payment_status'        => 'pending',
                'subtotal'              => $subtotal,
                'discount_amount'       => $discount,
                'delivery_fee'          => 0, // gratuit abonné
                'total'                 => $total,
                'subscription_id'       => $subscription->id,
                'is_subscription_order' => true,
                'is_priority'           => true,
            ]);

            foreach ($subscription->items as $item) {
                if (!$item->product || $item->product->stock <= 0) {
                    // Find substitute or skip
                    continue;
                }

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name,
                    'unit_price'   => $item->price,
                    'quantity'     => $item->quantity,
                    'total'        => $item->price * $item->quantity,
                ]);

                $item->product->decrementStock($item->quantity);
            }

            $subscription->increment('total_orders_generated');
            $subscription->update([
                'next_delivery_at' => $subscription->computeNextDelivery(),
            ]);

            // Notify user 24h before (should be job-based in real app)
            $order->user->notify(new \App\Notifications\SubscriptionOrderCreatedNotification($order));

            return $order;
        });
    }

    /**
     * Create an order from a selective subscription
     * NOUVEAU : pour les abonnements sélectifs personnalisés
     */
    public function createSelectiveSubscriptionOrder(SelectiveSubscription $subscription): Order
    {
        return DB::transaction(function () use ($subscription) {
            // Récupérer les articles actifs
            $activeItems = $subscription->items()
                ->where('is_active', true)
                ->with('product')
                ->get();

            if ($activeItems->isEmpty()) {
                throw new \Exception('Aucun article actif dans cet abonnement');
            }

            // Vérifier les stocks
            foreach ($activeItems as $item) {
                if (!$item->product) {
                    throw new \Exception("Produit #{$item->product_id} introuvable");
                }
                if ($item->product->stock < $item->quantity) {
                    throw new \Exception("Stock insuffisant pour le produit: {$item->product->name}");
                }
            }

            // Calculer les totaux
            $subtotal = $activeItems->sum(fn($item) => $item->price * $item->quantity);
            $discountAmount = $subtotal * ($subscription->discount_percent / 100);
            $total = $subtotal - $discountAmount;

            // Créer la commande
            $order = Order::create([
                'user_id'               => $subscription->user_id,
                'address_id'            => $subscription->address_id,
                'delivery_type'         => $subscription->delivery_type,
                'status'                => 'confirmed',
                'payment_method'        => $subscription->payment_method,
                'payment_status'        => $subscription->payment_method === 'auto' ? 'pending' : 'pending_manual',
                'subtotal'              => $subtotal,
                'discount_amount'       => $discountAmount,
                'delivery_fee'          => 0,
                'total'                 => $total,
                'selective_subscription_id' => $subscription->id,
                'notes'                 => "Commande générée depuis l'abonnement sélectif #{$subscription->id}",
                'is_priority'           => true,
            ]);

            // Créer les articles de la commande
            foreach ($activeItems as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product->name,
                    'product_sku'   => $item->product->sku,
                    'product_image' => $item->product->primary_image_url,
                    'unit_price'    => $item->price,
                    'compare_price' => $item->product->compare_price,
                    'quantity'      => $item->quantity,
                    'total'         => $item->price * $item->quantity,
                ]);

                // Réduire le stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Mettre à jour la prochaine date de livraison
            $subscription->update([
                'next_delivery_at' => $subscription->computeNextDelivery(),
            ]);

            // Notifier l'utilisateur
            try {
                $order->user->notify(new \App\Notifications\OrderConfirmedNotification($order));
            } catch (\Exception $e) {
                // Silencieux - ne pas bloquer la création si la notification échoue
            }

            // Mettre à jour les badges
            $this->loyaltyService->checkAndAwardBadges($subscription->user);

            return $order->fresh()->load(['items', 'address']);
        });
    }

    private function calculateDeliveryFee(float $subtotal, string $deliveryType): float
    {
        if ($deliveryType === 'click_collect') return 0;
        if ($subtotal >= 50000) return 0;
        if ($subtotal >= 20000) return 1000;
        return 2000;
    }
}