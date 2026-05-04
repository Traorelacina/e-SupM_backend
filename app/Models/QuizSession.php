<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Session de Quiz e-Sup'M
 * S'active automatiquement chaque mardi.
 * Ouvert à tous – chronométré – 1 participation / 3 jours si échec.
 */
class QuizSession extends Model
{
    protected $fillable = [
        'title',
        'theme',            // alimentaire | production | nutrition | culture | surprise
        'description',
        'image',
        'status',           // draft | active | closed
        'starts_at',
        'ends_at',
        'time_limit_seconds',
        'prize_description',
        'prize_image',
        'loyalty_points_prize',
        'min_score_to_win',  // % minimum pour gagner
        'retry_delay_hours', // délai avant nouvelle tentative (défaut 72)
    ];

    protected $casts = [
        'starts_at'          => 'datetime',
        'ends_at'            => 'datetime',
        'time_limit_seconds' => 'integer',
        'loyalty_points_prize'=> 'integer',
        'min_score_to_win'   => 'integer',
        'retry_delay_hours'  => 'integer',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(QuizParticipation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && now()->between($this->starts_at, $this->ends_at);
    }
}


/**
 * Question d'un quiz
 */
class QuizQuestion extends Model
{
    protected $fillable = [
        'quiz_session_id',
        'question_text',
        'question_image',
        'type',            // multiple_choice | true_false | text_input
        'points',
        'order',
        'explanation',     // explication après réponse
    ];

    protected $casts = [
        'points' => 'integer',
        'order'  => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class);
    }
}


/**
 * Option de réponse à une question de quiz
 */
class QuizOption extends Model
{
    protected $fillable = [
        'quiz_question_id',
        'option_text',
        'option_image',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order'      => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}


/**
 * Participation d'un utilisateur à un quiz
 */
class QuizParticipation extends Model
{
    protected $fillable = [
        'quiz_session_id',
        'user_id',
        'score',              // % de bonnes réponses
        'total_points',
        'answers',            // JSON { question_id => option_id }
        'time_taken_seconds',
        'completed_at',
        'won',
        'prize_description',
        'loyalty_points_won',
        'next_retry_at',      // quand l'utilisateur peut rejouer
    ];

    protected $casts = [
        'answers'          => 'array',
        'completed_at'     => 'datetime',
        'next_retry_at'    => 'datetime',
        'won'              => 'boolean',
        'score'            => 'decimal:2',
        'time_taken_seconds'=> 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}