<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Juste Prix e-Sup'M
 * Actif chaque jour – chronométré – 1 participation / 3 jours par utilisateur.
 * Ouvert à tous (sans achat préalable).
 * Le jeu diffère pour chaque utilisateur (produit aléatoire).
 */
class JustePrix extends Model
{
    protected $fillable = [
        'title',
        'status',            // active | closed
        'starts_at',
        'ends_at',
        'prize_description',
        'prize_image',
        'loyalty_points_prize',
        'tolerance_percent',  // tolérance de ±X% pour "presque juste"
    ];

    protected $casts = [
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
        'loyalty_points_prize'=> 'integer',
        'tolerance_percent'   => 'integer',
    ];

    public function participations(): HasMany
    {
        return $this->hasMany(JustePrixParticipation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && now()->between($this->starts_at, $this->ends_at);
    }
}


/**
 * Participation à un Juste Prix
 * Un produit aléatoire du catalogue est assigné à l'utilisateur.
 */
class JustePrixParticipation extends Model
{
    protected $fillable = [
        'juste_prix_id',
        'user_id',
        'product_id',
        'correct_price',
        'guessed_price',
        'time_limit_seconds',
        'time_taken_seconds',
        'is_correct',        // exactement bon
        'is_close',          // dans la tolérance
        'won',
        'prize_description',
        'loyalty_points_won',
        'next_allowed_at',   // quand l'utilisateur peut rejouer (+ 3 jours)
        'completed_at',
    ];

    protected $casts = [
        'correct_price'     => 'decimal:0',
        'guessed_price'     => 'decimal:0',
        'is_correct'        => 'boolean',
        'is_close'          => 'boolean',
        'won'               => 'boolean',
        'completed_at'      => 'datetime',
        'next_allowed_at'   => 'datetime',
        'time_limit_seconds'=> 'integer',
        'time_taken_seconds'=> 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(JustePrix::class, 'juste_prix_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Évalue si la proposition est correcte ou proche.
     */
    public function evaluate(int $tolerancePercent = 5): void
    {
        $diff      = abs($this->guessed_price - $this->correct_price);
        $tolerance = $this->correct_price * ($tolerancePercent / 100);

        $this->is_correct = $this->guessed_price == $this->correct_price;
        $this->is_close   = !$this->is_correct && $diff <= $tolerance;
        $this->won        = $this->is_correct; // seule la bonne réponse exacte gagne
        $this->save();
    }
}