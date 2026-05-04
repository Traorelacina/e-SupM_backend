<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * e-Sup'M Battle (vote)
 * S'active automatiquement chaque mercredi.
 * Types : promo | product | team
 * 1 vote par utilisateur par battle – impossible de re-voter sur le même jeu.
 */
class BattleContest extends Model
{
    protected $fillable = [
        'title',
        'type',              // promo | product | team
        'description',
        'image',
        'status',            // draft | active | closed
        'starts_at',
        'ends_at',
        'winner_candidate_id',
        'prize_description',
        'loyalty_points_prize',
    ];

    protected $casts = [
        'starts_at'          => 'datetime',
        'ends_at'            => 'datetime',
        'loyalty_points_prize'=> 'integer',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(BattleCandidate::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(BattleVote::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(BattleCandidate::class, 'winner_candidate_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && now()->between($this->starts_at, $this->ends_at);
    }

    public function hasUserVoted(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    /**
     * Calcule et enregistre le gagnant selon le nombre de votes.
     */
    public function computeWinner(): ?BattleCandidate
    {
        $winner = $this->candidates()->orderByDesc('votes_count')->first();
        if ($winner) {
            $this->update(['winner_candidate_id' => $winner->id, 'status' => 'closed']);
        }
        return $winner;
    }
}


/**
 * Candidat à un battle (produit, promo, team…)
 */
class BattleCandidate extends Model
{
    protected $fillable = [
        'battle_contest_id',
        'name',
        'image',
        'description',
        'votes_count',
        'order',
    ];

    protected $casts = [
        'votes_count' => 'integer',
        'order'       => 'integer',
    ];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(BattleContest::class, 'battle_contest_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(BattleVote::class, 'candidate_id');
    }
}


/**
 * Vote d'un utilisateur dans un battle
 */
class BattleVote extends Model
{
    protected $fillable = [
        'battle_contest_id',
        'candidate_id',
        'user_id',
    ];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(BattleContest::class, 'battle_contest_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(BattleCandidate::class, 'candidate_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}