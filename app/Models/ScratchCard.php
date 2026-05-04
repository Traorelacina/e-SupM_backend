<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carte à gratter digitale e-Sup'M
 *
 * Déclenchement :
 *   – Achat cumulé ≥ 15 000 FCFA/mois   → scratch standard (1 fois/mois max)
 *   – Don charity ≥ 5 000 FCFA          → scratch charity (indépendant)
 *
 * Lots possibles : product | points | message (retenter) | travel | hotel | voucher
 */
class ScratchCard extends Model
{
    protected $fillable = [
        'user_id',
        'month_year',           // YYYY-MM
        'trigger_type',         // purchase | charity
        'trigger_amount',
        'is_scratched',
        'scratched_at',
        'prize_type',           // product | points | message | travel | hotel | voucher | empty
        'prize_label',          // "Vous avez gagné un voyage !"
        'prize_value',          // points amount, voucher amount, etc.
        'prize_description',
        'prize_image',
        'prize_claimed',
        'prize_claimed_at',
        'expires_at',
    ];

    protected $casts = [
        'is_scratched'   => 'boolean',
        'prize_claimed'  => 'boolean',
        'scratched_at'   => 'datetime',
        'prize_claimed_at'=> 'datetime',
        'expires_at'     => 'datetime',
        'trigger_amount' => 'decimal:0',
        'prize_value'    => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return !$this->is_scratched
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Liste pondérée des lots → tirage aléatoire
     */
    public static function drawPrize(string $triggerType = 'purchase'): array
    {
        $prizes = [
            ['type' => 'points',  'label' => 'Vous gagnez 500 points fidélité !',       'value' => 500,   'weight' => 30],
            ['type' => 'points',  'label' => 'Vous gagnez 1 000 points fidélité !',     'value' => 1000,  'weight' => 20],
            ['type' => 'voucher', 'label' => 'Bon de réduction de 1 000 FCFA !',         'value' => 1000,  'weight' => 15],
            ['type' => 'voucher', 'label' => 'Bon de réduction de 2 500 FCFA !',         'value' => 2500,  'weight' => 10],
            ['type' => 'product', 'label' => 'Livraison gratuite sur votre prochaine commande !', 'value' => 1, 'weight' => 10],
            ['type' => 'travel',  'label' => 'Vous gagnez un séjour à l\'hôtel ! 🏨',    'value' => 1,     'weight' => 2],
            ['type' => 'empty',   'label' => 'Retentez votre chance une prochaine fois !', 'value' => 0,   'weight' => 13],
        ];

        $totalWeight = array_sum(array_column($prizes, 'weight'));
        $rand = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($prizes as $prize) {
            $cumulative += $prize['weight'];
            if ($rand <= $cumulative) {
                return $prize;
            }
        }

        return end($prizes);
    }
}