<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectiveSubscriptionItem extends Model
{
    use HasFactory;

    protected $table = 'selective_subscription_items';

    protected $fillable = [
        'selective_subscription_id',
        'product_id',
        'quantity',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────

    /**
     * Abonnement parent
     */
    public function selectiveSubscription(): BelongsTo
    {
        return $this->belongsTo(SelectiveSubscription::class, 'selective_subscription_id');
    }

    /**
     * Produit associé
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Méthodes
    // ─────────────────────────────────────────────────────────────

    /**
     * Calcule le prix total de l'article (prix × quantité)
     */
    public function getLineTotal(): float
    {
        return $this->is_active ? round($this->price * $this->quantity, 2) : 0;
    }

    /**
     * Active l'article
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Désactive l'article
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Vérifie si le produit associé est en stock
     */
    public function isProductInStock(): bool
    {
        return $this->product && $this->product->stock >= $this->quantity;
    }
}