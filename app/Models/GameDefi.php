<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeu e-Sup'M Défis
 * S'active automatiquement chaque jeudi pour 2 semaines.
 * Ouvert à tous – inscription + vote requis.
 */
class GameDefi extends Model
{
    protected $fillable = [
        'title',
        'description',
        'challenge_text',
        'challenge_video_url',
        'image',
        'status',            // draft | active | voting | closed
        'starts_at',
        'ends_at',
        'voting_ends_at',
        'winner_participant_id',
        'prize_description',
        'prize_image',
        'loyalty_points_prize',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'voting_ends_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────

    public function participants(): HasMany
    {
        return $this->hasMany(GameDefiParticipant::class, 'game_defi_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(GameDefiParticipant::class, 'winner_participant_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVoting($query)
    {
        return $query->where('status', 'voting');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'active' && now()->between($this->starts_at, $this->ends_at);
    }

    public function isVoting(): bool
    {
        return $this->status === 'voting' && now()->lte($this->voting_ends_at);
    }
}