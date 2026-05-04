<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Participant à un défi e-Sup'M
 */
class GameDefiParticipant extends Model
{
    protected $fillable = [
        'game_defi_id',
        'user_id',
        'submission_text',
        'submission_image',
        'submission_video_url',
        'votes_count',
        'is_selected',       // sélectionné par admin pour affichage vote
        'is_winner',
        'prize_claimed',
        'earned_at',
        'admin_note',
    ];

    protected $casts = [
        'is_selected'  => 'boolean',
        'is_winner'    => 'boolean',
        'prize_claimed'=> 'boolean',
        'earned_at'    => 'datetime',
    ];

    public function gameDefi(): BelongsTo
    {
        return $this->belongsTo(GameDefi::class, 'game_defi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(GameDefiVote::class, 'participant_id');
    }

    public function hasVotedBy(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }
}


/**
 * Vote sur un participant à un défi
 */
class GameDefiVote extends Model
{
    protected $fillable = [
        'game_defi_id',
        'participant_id',
        'user_id',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(GameDefiParticipant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}