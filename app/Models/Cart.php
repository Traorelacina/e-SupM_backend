<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'coupon_code',
        'coupon_discount',
        'expires_at',
    ];

    protected $casts = [
        'coupon_discount' => 'float',
        'expires_at'      => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // ── Accesseurs ───────────────────────────────────────────────────────────

    /**
     * Calcule le sous-total du panier (somme des line_total des items).
     * Utilise price * quantity pour être sûr même si line_total n'est pas chargé.
     */
    public function getSubtotalAttribute(): float
    {
        return (float) $this->items->sum(fn($i) => (float) $i->price * (int) $i->quantity);
    }

    /**
     * Total = subtotal - coupon_discount (jamais négatif).
     * NOTE : la livraison est calculée séparément dans CartService::getCartSummary().
     */
    public function getTotalAttribute(): float
    {
        return (float) max(0, $this->subtotal - (float) ($this->coupon_discount ?? 0));
    }

    /**
     * Nombre total d'articles (somme des quantités).
     */
    public function getTotalItemsAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Paniers non expirés.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}