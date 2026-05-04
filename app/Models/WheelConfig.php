<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configuration de la Roue e-Sup'M
 *
 * wheel_type = 'wholesale'  → Grossiste/½ grossiste ≥ 50 000 FCFA – 2x/mois
 * wheel_type = 'standard'   → Cumul mensuel ≥ 15 000 FCFA – 1x/mois
 */
class WheelConfig extends Model
{
    protected $fillable = [
        'name',
        'wheel_type',            // wholesale | standard
        'min_purchase_amount',
        'spins_per_month',       // 2 pour wholesale, 1 pour standard
        'is_active',
        'prizes',                // JSON array
    ];

    protected $casts = [
        'prizes'             => 'array',
        'is_active'          => 'boolean',
        'min_purchase_amount'=> 'decimal:0',
    ];

    public function spins(): HasMany
    {
        return $this->hasMany(WheelSpin::class);
    }

    /**
     * Retourne les prix par défaut selon le type de roue.
     */
    public static function defaultPrizes(string $wheelType): array
    {
        if ($wheelType === 'wholesale') {
            return [
                ['label' => 'Livraison gratuite',          'type' => 'delivery',  'value' => 1,    'color' => '#22c55e', 'weight' => 20],
                ['label' => '5 000 pts fidélité',           'type' => 'points',    'value' => 5000, 'color' => '#f59e0b', 'weight' => 15],
                ['label' => '10 000 pts fidélité',          'type' => 'points',    'value' => 10000,'color' => '#8b5cf6', 'weight' => 8],
                ['label' => 'Bon 5 000 FCFA',               'type' => 'voucher',   'value' => 5000, 'color' => '#ef4444', 'weight' => 10],
                ['label' => 'Bon 10 000 FCFA',              'type' => 'voucher',   'value' => 10000,'color' => '#3b82f6', 'weight' => 5],
                ['label' => 'Séjour hôtel offert 🏨',       'type' => 'travel',    'value' => 1,    'color' => '#ec4899', 'weight' => 2],
                ['label' => 'Retentez votre chance',        'type' => 'empty',     'value' => 0,    'color' => '#6b7280', 'weight' => 40],
            ];
        }

        return [
            ['label' => 'Livraison gratuite',       'type' => 'delivery', 'value' => 1,    'color' => '#22c55e', 'weight' => 25],
            ['label' => '500 pts fidélité',          'type' => 'points',   'value' => 500,  'color' => '#f59e0b', 'weight' => 25],
            ['label' => '1 000 pts fidélité',        'type' => 'points',   'value' => 1000, 'color' => '#8b5cf6', 'weight' => 15],
            ['label' => 'Bon 1 000 FCFA',            'type' => 'voucher',  'value' => 1000, 'color' => '#ef4444', 'weight' => 12],
            ['label' => 'Bon 2 500 FCFA',            'type' => 'voucher',  'value' => 2500, 'color' => '#3b82f6', 'weight' => 6],
            ['label' => 'Produit offert 🎁',         'type' => 'product',  'value' => 1,    'color' => '#ec4899', 'weight' => 5],
            ['label' => 'Retentez votre chance',     'type' => 'empty',    'value' => 0,    'color' => '#6b7280', 'weight' => 12],
        ];
    }
}


/**
 * Enregistrement d'un tour de roue
 */
class WheelSpin extends Model
{
    protected $fillable = [
        'user_id',
        'wheel_config_id',
        'month_year',        // YYYY-MM
        'spin_number',       // 1 ou 2 pour wholesale
        'prize_label',
        'prize_type',
        'prize_value',
        'prize_claimed',
        'prize_claimed_at',
        'triggered_by',      // purchase_threshold | manual_admin
        'trigger_order_id',
    ];

    protected $casts = [
        'prize_claimed'    => 'boolean',
        'prize_claimed_at' => 'datetime',
        'prize_value'      => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wheelConfig(): BelongsTo
    {
        return $this->belongsTo(WheelConfig::class);
    }
}