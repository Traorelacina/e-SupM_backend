<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectiveSubscription extends Model
{
    use HasFactory;

    protected $table = 'selective_subscriptions';

    protected $fillable = [
        'user_id',
        'name',
        'frequency',
        'delivery_day',
        'delivery_week_of_month',
        'delivery_type',
        'address_id',
        'payment_method',
        'status',
        'discount_percent',
        'subtotal',
        'total',
        'next_delivery_at',
        'suspended_until',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    protected $casts = [
        'discount_percent' => 'float',
        'subtotal' => 'float',
        'total' => 'float',
        'delivery_day' => 'integer',
        'delivery_week_of_month' => 'integer',
        'next_delivery_at' => 'datetime',
        'suspended_until' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────

    /**
     * Utilisateur propriétaire de l'abonnement
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Articles de l'abonnement sélectif
     */
    public function items(): HasMany
    {
        return $this->hasMany(SelectiveSubscriptionItem::class, 'selective_subscription_id')
            ->orderBy('sort_order');
    }

    /**
     * Adresse de livraison
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Commandes générées à partir de cet abonnement
     * CORRIGÉ : Utilise une relation directe HasMany
     * Nécessite la colonne 'selective_subscription_id' dans la table orders
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'selective_subscription_id');
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    /**
     * Abonnements actifs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Abonnements suspendus
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Abonnements annulés
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Abonnements avec paiement automatique
     */
    public function scopeAutoPayment($query)
    {
        return $query->where('payment_method', 'auto');
    }

    /**
     * Abonnements avec livraison à venir dans X jours
     */
    public function scopeUpcomingDelivery($query, int $days = 3)
    {
        return $query->where('status', 'active')
            ->where('next_delivery_at', '<=', now()->addDays($days))
            ->where('next_delivery_at', '>', now());
    }

    // ─────────────────────────────────────────────────────────────
    // Méthodes
    // ─────────────────────────────────────────────────────────────

    /**
     * Calcule la prochaine date de livraison en fonction de la fréquence
     */
    public function computeNextDelivery(): ?\Carbon\Carbon
    {
        $now = now();
        $baseDate = $this->next_delivery_at ?? $now;

        switch ($this->frequency) {
            case 'weekly':
                $next = $baseDate->copy()->addWeek();
                if ($this->delivery_day && $this->delivery_day >= 1 && $this->delivery_day <= 7) {
                    $next = $next->setISODate($next->year, $next->weekOfYear, $this->delivery_day);
                }
                return $next;

            case 'biweekly':
                $next = $baseDate->copy()->addWeeks(2);
                if ($this->delivery_day && $this->delivery_day >= 1 && $this->delivery_day <= 7) {
                    $next = $next->setISODate($next->year, $next->weekOfYear, $this->delivery_day);
                }
                return $next;

            case 'monthly':
                $next = $baseDate->copy()->addMonth();

                if ($this->delivery_week_of_month && $this->delivery_day) {
                    $targetWeek = min(max(1, $this->delivery_week_of_month), 4);
                    $firstDayOfMonth = $next->copy()->startOfMonth();
                    $weekStart = $firstDayOfMonth->copy()->addWeeks($targetWeek - 1);
                    $targetDay = $weekStart->copy()->setISODate($weekStart->year, $weekStart->weekOfYear, $this->delivery_day);
                    
                    if ($targetDay->month !== $next->month) {
                        $targetDay = $targetDay->subWeek();
                    }
                    
                    return $targetDay;
                }
                return $next;

            default:
                return $baseDate->copy()->addWeek();
        }
    }

    /**
     * Vérifie si l'abonnement doit être traité aujourd'hui
     */
    public function shouldProcessToday(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (!$this->next_delivery_at) {
            return false;
        }

        return $this->next_delivery_at->isToday() || $this->next_delivery_at->isPast();
    }

    /**
     * Vérifie si l'abonnement a des articles actifs
     */
    public function hasActiveItems(): bool
    {
        return $this->items()->where('is_active', true)->exists();
    }

    /**
     * Récupère le nombre d'articles actifs
     */
    public function getActiveItemsCount(): int
    {
        return $this->items()->where('is_active', true)->count();
    }

    /**
     * Calcule le sous-total des articles actifs
     */
    public function calculateSubtotal(): float
    {
        return (float) $this->items()
            ->where('is_active', true)
            ->get()
            ->sum(fn($item) => $item->price * $item->quantity);
    }

    /**
     * Calcule le total avec remise
     */
    public function calculateTotal(): float
    {
        $subtotal = $this->calculateSubtotal();
        return round($subtotal * (1 - ($this->discount_percent / 100)), 2);
    }

    /**
     * Recalcule et met à jour les totaux
     */
    public function refreshTotals(): void
    {
        $subtotal = $this->calculateSubtotal();
        $total = $this->calculateTotal();

        $this->update([
            'subtotal' => $subtotal,
            'total' => $total,
        ]);
    }

    /**
     * Vérifie si l'abonnement est en période de suspension
     */
    public function isCurrentlySuspended(): bool
    {
        if ($this->status !== 'suspended') {
            return false;
        }

        if ($this->suspended_until && $this->suspended_until->isPast()) {
            $this->update(['status' => 'active', 'suspended_until' => null]);
            return false;
        }

        return true;
    }
}