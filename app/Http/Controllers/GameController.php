<?php

namespace App\Http\Controllers;

use App\Models\BattleContest;
use App\Models\BattleVote;
use App\Models\GameDefi;
use App\Models\GameDefiParticipant;
use App\Models\JustePrix;
use App\Models\JustePrixParticipation;
use App\Models\QuizParticipation;
use App\Models\QuizQuestion;
use App\Models\QuizSession;
use App\Models\ScratchCard;
use App\Models\WheelConfig;
use App\Models\WheelSpin;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    // ═══════════════════════════════════════════════════════════════
    //  DÉFIS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Liste les défis publics (actifs + en vote).
     * GET /games/defis
     */
    public function defiIndex(): JsonResponse
    {
        $defis = GameDefi::whereIn('status', ['active', 'voting', 'closed'])
            ->withCount('participants')
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $defis]);
    }

    /**
     * Détail d'un défi avec les participants sélectionnés pour le vote.
     * GET /games/defis/{id}
     */
    public function defiShow(int $id): JsonResponse
    {
        $defi = GameDefi::with([
            'participants' => fn($q) => $q
                ->where('is_selected', true)
                ->with('user:id,name,avatar')
                ->withCount('votes')
                ->orderByDesc('votes_count'),
        ])
            ->withCount('participants')
            ->findOrFail($id);

        // Ne pas exposer les données sensibles
        $defi->makeHidden(['winner_participant_id']);

        return response()->json(['success' => true, 'data' => $defi]);
    }

    /**
     * Soumettre sa participation à un défi.
     * POST /games/defis/{id}/participate
     */
    public function defiParticipate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $defi = GameDefi::where('status', 'active')->findOrFail($id);

        // Un utilisateur ne peut participer qu'une seule fois
        if (GameDefiParticipant::where('game_defi_id', $id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Vous participez déjà à ce défi.'], 422);
        }

        $data = $request->validate([
            'submission_text'  => ['required_without:submission_video_url', 'nullable', 'string', 'max:2000'],
            'submission_video_url' => ['required_without:submission_text', 'nullable', 'url'],
        ]);

        $participantData = [
            'game_defi_id'         => $id,
            'user_id'              => $user->id,
            'submission_text'      => $data['submission_text'] ?? null,
            'submission_video_url' => $data['submission_video_url'] ?? null,
        ];

        if ($request->hasFile('submission_image')) {
            $participantData['submission_image'] = $request->file('submission_image')
                ->store('games/defis/submissions', 'public');
        }

        $participant = GameDefiParticipant::create($participantData);

        return response()->json([
            'success' => true,
            'message' => 'Votre participation a bien été enregistrée !',
            'data'    => $participant,
        ], 201);
    }

    /**
     * Voter pour un participant (phase de vote uniquement).
     * POST /games/defis/{id}/vote
     */
    public function defiVote(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $defi = GameDefi::where('status', 'voting')->findOrFail($id);

        $request->validate([
            'participant_id' => ['required', 'exists:game_defi_participants,id'],
        ]);

        // Vérifier que le participant appartient bien à ce défi et est sélectionné
        $participant = GameDefiParticipant::where('game_defi_id', $id)
            ->where('is_selected', true)
            ->findOrFail($request->participant_id);

        // Un utilisateur ne peut voter qu'une seule fois par défi
        $alreadyVoted = \App\Models\GameDefiVote::where('game_defi_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyVoted) {
            return response()->json(['message' => 'Vous avez déjà voté pour ce défi.'], 422);
        }

        // Un utilisateur ne peut pas voter pour lui-même
        if ($participant->user_id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas voter pour votre propre participation.'], 422);
        }

        DB::transaction(function () use ($id, $user, $participant) {
            \App\Models\GameDefiVote::create([
                'game_defi_id'   => $id,
                'participant_id' => $participant->id,
                'user_id'        => $user->id,
            ]);
            $participant->increment('votes_count');
        });

        return response()->json(['success' => true, 'message' => 'Votre vote a bien été enregistré !']);
    }

    /**
     * Vérifie si l'utilisateur a déjà participé / voté.
     * GET /games/defis/{id}/status
     */
    public function defiUserStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $participation = GameDefiParticipant::where('game_defi_id', $id)
            ->where('user_id', $user->id)
            ->first();

        $voted = \App\Models\GameDefiVote::where('game_defi_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'success'       => true,
            'has_participated' => (bool) $participation,
            'has_voted'     => $voted,
            'participation' => $participation,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  CARTE À GRATTER
    // ═══════════════════════════════════════════════════════════════

    /**
     * Cartes à gratter de l'utilisateur connecté.
     * GET /games/scratch
     */
    public function scratchIndex(Request $request): JsonResponse
    {
        $cards = ScratchCard::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $cards]);
    }

    /**
     * Gratter une carte (révèle le lot).
     * POST /games/scratch/{id}/scratch
     */
    public function scratchReveal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $card = ScratchCard::where('user_id', $user->id)
            ->where('is_scratched', false)
            ->findOrFail($id);

        // Vérifier que la carte n'est pas expirée
        if ($card->expires_at && $card->expires_at->isPast()) {
            return response()->json(['message' => 'Cette carte est expirée.'], 422);
        }

        DB::transaction(function () use ($card, $user) {
            $card->update([
                'is_scratched' => true,
                'scratched_at' => now(),
            ]);

            // Attribuer automatiquement les points si le lot est en points
            if ($card->prize_type === 'points' && $card->prize_value > 0) {
                $this->loyaltyService->awardPoints(
                    $user,
                    $card->prize_value,
                    'game_win',
                    "Lot carte à gratter : {$card->prize_label}",
                );
            }
        });

        return response()->json([
            'success'    => true,
            'message'    => $card->prize_type === 'empty' ? 'Pas de chance cette fois !' : 'Félicitations ! Vous avez gagné un lot !',
            'prize_type'  => $card->prize_type,
            'prize_label' => $card->prize_label,
            'prize_value' => $card->prize_value,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ROUE E-SUP'M
    // ═══════════════════════════════════════════════════════════════

    /**
     * Configuration(s) de roue disponibles pour l'utilisateur.
     * GET /games/wheel
     */
    public function wheelAvailable(Request $request): JsonResponse
    {
        $user    = $request->user();
        $configs = WheelConfig::where('is_active', true)->get();

        $monthYear = now()->format('Y-m');

        $result = $configs->map(function (WheelConfig $config) use ($user, $monthYear) {
            $spinsUsed = WheelSpin::where('user_id', $user->id)
                ->where('wheel_config_id', $config->id)
                ->where('month_year', $monthYear)
                ->count();

            return [
                'id'             => $config->id,
                'name'           => $config->name,
                'wheel_type'     => $config->wheel_type,
                'prizes'         => $config->prizes, // poids masqués côté client si nécessaire
                'spins_used'     => $spinsUsed,
                'spins_per_month'=> $config->spins_per_month,
                'can_spin'       => $spinsUsed < $config->spins_per_month,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Lancer la roue (tour utilisateur).
     * POST /games/wheel/{configId}/spin
     */
    public function wheelSpin(Request $request, int $configId): JsonResponse
    {
        $user   = $request->user();
        $config = WheelConfig::where('is_active', true)->findOrFail($configId);

        $monthYear = now()->format('Y-m');
        $spinsUsed = WheelSpin::where('user_id', $user->id)
            ->where('wheel_config_id', $configId)
            ->where('month_year', $monthYear)
            ->count();

        if ($spinsUsed >= $config->spins_per_month) {
            return response()->json([
                'message' => "Vous avez utilisé tous vos tours pour ce mois ({$config->spins_per_month}/{$config->spins_per_month}).",
            ], 422);
        }

        // Tirage au sort pondéré
        $prizes = $config->prizes;
        $totalW = array_sum(array_column($prizes, 'weight'));
        $rand   = random_int(1, $totalW);
        $cum    = 0;
        $prize  = end($prizes);

        foreach ($prizes as $p) {
            $cum += $p['weight'];
            if ($rand <= $cum) { $prize = $p; break; }
        }

        $spin = DB::transaction(function () use ($user, $config, $configId, $monthYear, $spinsUsed, $prize) {
            $spin = WheelSpin::create([
                'user_id'         => $user->id,
                'wheel_config_id' => $configId,
                'month_year'      => $monthYear,
                'spin_number'     => $spinsUsed + 1,
                'prize_label'     => $prize['label'],
                'prize_type'      => $prize['type'],
                'prize_value'     => $prize['value'] ?? 0,
                'triggered_by'    => 'user',
            ]);

            if ($prize['type'] === 'points' && ($prize['value'] ?? 0) > 0) {
                $this->loyaltyService->awardPoints(
                    $user,
                    $prize['value'],
                    'game_win',
                    "Lot roue e-Sup'M : {$prize['label']}",
                );
            }

            return $spin;
        });

        return response()->json([
            'success'     => true,
            'message'     => $prize['type'] === 'empty' ? 'Pas de chance cette fois !' : 'Félicitations !',
            'prize'       => [
                'type'    => $prize['type'],
                'label'   => $prize['label'],
                'value'   => $prize['value'] ?? 0,
                'color'   => $prize['color'] ?? null,
            ],
            'spins_left'  => $config->spins_per_month - ($spinsUsed + 1),
        ]);
    }

    /**
     * Historique des tours de roue de l'utilisateur.
     * GET /games/wheel/history
     */
    public function wheelHistory(Request $request): JsonResponse
    {
        $spins = WheelSpin::where('user_id', $request->user()->id)
            ->with('wheelConfig:id,name')
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $spins]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  QUIZ
    // ═══════════════════════════════════════════════════════════════

    /**
     * Liste les quiz actifs disponibles.
     * GET /games/quiz
     */
    public function quizIndex(): JsonResponse
    {
        $sessions = QuizSession::where('status', 'active')
            ->whereDate('ends_at', '>=', today())
            ->withCount('questions')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    /**
     * Détail d'un quiz avec les questions (sans révéler les bonnes réponses).
     * GET /games/quiz/{id}
     */
    public function quizShow(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $session = QuizSession::where('status', 'active')->findOrFail($id);

        // Vérifier si l'utilisateur peut rejouer (délai de réessai)
        $lastParticipation = QuizParticipation::where('quiz_session_id', $id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $canPlay    = true;
        $nextPlayAt = null;

        if ($lastParticipation) {
            $waitUntil = $lastParticipation->created_at->addHours($session->retry_delay_hours ?? 72);
            if (now()->lt($waitUntil)) {
                $canPlay    = false;
                $nextPlayAt = $waitUntil->toIso8601String();
            }
        }

        // Questions sans les bonnes réponses exposées
        $questions = $session->questions()
            ->with(['options' => fn($q) => $q->select('id', 'quiz_question_id', 'option_text', 'order')->orderBy('order')])
            ->orderBy('order')
            ->get()
            ->map(fn($q) => $q->makeHidden(['correct_answer']));

        return response()->json([
            'success'     => true,
            'data'        => array_merge($session->only(['id', 'title', 'theme', 'description', 'time_limit_seconds', 'prize_description', 'loyalty_points_prize']), [
                'questions'          => $questions,
                'can_play'           => $canPlay,
                'next_play_at'       => $nextPlayAt,
                'participations_count' => QuizParticipation::where('quiz_session_id', $id)->count(),
            ]),
        ]);
    }

    /**
     * Soumettre les réponses d'un quiz.
     * POST /games/quiz/{id}/submit
     */
    public function quizSubmit(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $session = QuizSession::where('status', 'active')
            ->whereDate('ends_at', '>=', today())
            ->findOrFail($id);

        // Vérifier le délai de réessai
        $lastParticipation = QuizParticipation::where('quiz_session_id', $id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if ($lastParticipation) {
            $waitUntil = $lastParticipation->created_at->addHours($session->retry_delay_hours ?? 72);
            if (now()->lt($waitUntil)) {
                return response()->json([
                    'message'     => 'Vous devez attendre avant de rejouer.',
                    'next_play_at' => $waitUntil->toIso8601String(),
                ], 422);
            }
        }

        $request->validate([
            'answers'              => ['required', 'array'],
            'answers.*.question_id'=> ['required', 'integer'],
            'answers.*.option_id'  => ['nullable', 'integer'],
            'answers.*.text'       => ['nullable', 'string'],
            'time_taken_seconds'   => ['nullable', 'integer', 'min:0'],
        ]);

        // Correction
        $questions     = $session->questions()->with('options')->get()->keyBy('id');
        $totalPoints   = 0;
        $earnedPoints  = 0;
        $correctCount  = 0;
        $results       = [];

        foreach ($request->answers as $answer) {
            $question = $questions->get($answer['question_id']);
            if (!$question) continue;

            $totalPoints += $question->points;
            $isCorrect    = false;

            if ($question->type === 'multiple_choice' || $question->type === 'true_false') {
                $correctOption = $question->options->firstWhere('is_correct', true);
                $isCorrect     = $correctOption && (int) ($answer['option_id'] ?? 0) === $correctOption->id;
            } elseif ($question->type === 'text_input') {
                $isCorrect = isset($question->correct_answer)
                    && mb_strtolower(trim($answer['text'] ?? '')) === mb_strtolower(trim($question->correct_answer));
            }

            if ($isCorrect) {
                $earnedPoints += $question->points;
                $correctCount++;
            }

            $results[] = [
                'question_id' => $question->id,
                'is_correct'  => $isCorrect,
                'points'      => $isCorrect ? $question->points : 0,
                'explanation' => $question->explanation,
            ];
        }

        $scorePercent = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100) : 0;
        $won          = $scorePercent >= ($session->min_score_to_win ?? 100);

        $participation = DB::transaction(function () use ($session, $user, $earnedPoints, $scorePercent, $won, $correctCount, $questions, $request) {
            $participation = QuizParticipation::create([
                'quiz_session_id'    => $session->id,
                'user_id'            => $user->id,
                'score'              => $earnedPoints,
                'score_percent'      => $scorePercent,
                'won'                => $won,
                'correct_answers'    => $correctCount,
                'total_questions'    => $questions->count(),
                'time_taken_seconds' => $request->time_taken_seconds ?? null,
            ]);

            if ($won && $session->loyalty_points_prize) {
                $this->loyaltyService->awardPoints(
                    $user,
                    $session->loyalty_points_prize,
                    'game_win',
                    "Quiz gagné : {$session->title}",
                );
            }

            return $participation;
        });

        return response()->json([
            'success'       => true,
            'score'         => $earnedPoints,
            'score_percent' => $scorePercent,
            'correct_count' => $correctCount,
            'total'         => $questions->count(),
            'won'           => $won,
            'results'       => $results,
            'message'       => $won ? '🏆 Félicitations, vous avez gagné !' : "Dommage ! Vous avez obtenu {$scorePercent}%.",
        ]);
    }

    /**
     * Classement d'un quiz.
     * GET /games/quiz/{id}/leaderboard
     */
    public function quizLeaderboard(int $id): JsonResponse
    {
        $leaderboard = QuizParticipation::where('quiz_session_id', $id)
            ->with('user:id,name,avatar')
            ->orderByDesc('score_percent')
            ->orderBy('time_taken_seconds')
            ->limit(20)
            ->get()
            ->map(fn($p, $i) => [
                'rank'           => $i + 1,
                'user'           => $p->user,
                'score_percent'  => $p->score_percent,
                'correct_answers'=> $p->correct_answers,
                'time_taken'     => $p->time_taken_seconds,
                'won'            => $p->won,
            ]);

        return response()->json(['success' => true, 'data' => $leaderboard]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  BATTLE (VOTE)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Liste les battles actifs.
     * GET /games/battle
     */
    public function battleIndex(): JsonResponse
    {
        $battles = BattleContest::whereIn('status', ['active', 'closed'])
            ->with(['candidates' => fn($q) => $q->orderByDesc('votes_count')])
            ->withCount('votes')
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $battles]);
    }

    /**
     * Détail d'un battle.
     * GET /games/battle/{id}
     */
    public function battleShow(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $battle  = BattleContest::with(['candidates' => fn($q) => $q->orderByDesc('votes_count')])
            ->withCount('votes')
            ->findOrFail($id);

        $userVote = BattleVote::where('battle_contest_id', $id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'success'      => true,
            'data'         => $battle,
            'user_vote'    => $userVote ? ['candidate_id' => $userVote->candidate_id] : null,
            'has_voted'    => (bool) $userVote,
        ]);
    }

    /**
     * Voter pour un candidat.
     * POST /games/battle/{id}/vote
     */
    public function battleVote(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $battle = BattleContest::where('status', 'active')->findOrFail($id);

        $request->validate([
            'candidate_id' => ['required', 'exists:battle_candidates,id'],
        ]);

        // Vérifier que le candidat appartient bien à ce battle
        $candidate = $battle->candidates()->findOrFail($request->candidate_id);

        // Un seul vote par utilisateur par battle
        $alreadyVoted = BattleVote::where('battle_contest_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyVoted) {
            return response()->json(['message' => 'Vous avez déjà voté pour ce battle.'], 422);
        }

        DB::transaction(function () use ($id, $user, $candidate) {
            BattleVote::create([
                'battle_contest_id' => $id,
                'candidate_id'      => $candidate->id,
                'user_id'           => $user->id,
            ]);
            $candidate->increment('votes_count');
        });

        return response()->json([
            'success'     => true,
            'message'     => 'Votre vote a bien été enregistré !',
            'candidate'   => $candidate->fresh(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  JUSTE PRIX
    // ═══════════════════════════════════════════════════════════════

    /**
     * Session Juste Prix active.
     * GET /games/justeprix
     */
    public function justePrixIndex(): JsonResponse
    {
        $sessions = JustePrix::where('status', 'active')
            ->whereDate('ends_at', '>=', today())
            ->withCount(['participations', 'participations as winners_count' => fn($q) => $q->where('won', true)])
            ->get();

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    /**
     * Détail d'une session avec les produits.
     * GET /games/justeprix/{id}
     */
    public function justePrixShow(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $session = JustePrix::where('status', 'active')
            ->with('products:id,name,primary_image')  // les produits dont on doit deviner le prix
            ->withCount(['participations', 'participations as winners_count' => fn($q) => $q->where('won', true)])
            ->findOrFail($id);

        $userParticipation = JustePrixParticipation::where('juste_prix_id', $id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'success'           => true,
            'data'              => $session->makeHidden(['products.price']), // ne pas exposer le prix
            'has_participated'  => (bool) $userParticipation,
            'participation'     => $userParticipation,
        ]);
    }

    /**
     * Soumettre une estimation de prix.
     * POST /games/justeprix/{id}/participate
     */
    public function justePrixParticipate(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $session = JustePrix::where('status', 'active')
            ->whereDate('ends_at', '>=', today())
            ->findOrFail($id);

        // Un seul essai par session
        if (JustePrixParticipation::where('juste_prix_id', $id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Vous avez déjà participé à cette session.'], 422);
        }

        $request->validate([
            'product_id'    => ['required', 'exists:products,id'],
            'guessed_price' => ['required', 'numeric', 'min:0'],
        ]);

        $product      = \App\Models\Product::findOrFail($request->product_id);
        $realPrice    = $product->price;
        $guessedPrice = (float) $request->guessed_price;
        $tolerance    = $session->tolerance_percent ?? 5;

        // Calcul de l'écart en pourcentage
        $diffPercent = abs($guessedPrice - $realPrice) / $realPrice * 100;
        $won         = $diffPercent <= $tolerance;

        $participation = DB::transaction(function () use ($session, $user, $product, $guessedPrice, $realPrice, $diffPercent, $won) {
            $participation = JustePrixParticipation::create([
                'juste_prix_id' => $session->id,
                'user_id'       => $user->id,
                'product_id'    => $product->id,
                'guessed_price' => $guessedPrice,
                'real_price'    => $realPrice,
                'diff_percent'  => round($diffPercent, 2),
                'won'           => $won,
            ]);

            if ($won && $session->loyalty_points_prize) {
                $this->loyaltyService->awardPoints(
                    $user,
                    $session->loyalty_points_prize,
                    'game_win',
                    "Juste Prix gagné !",
                );
            }

            return $participation;
        });

        return response()->json([
            'success'      => true,
            'won'          => $won,
            'guessed_price'=> $guessedPrice,
            'real_price'   => $realPrice,
            'diff_percent' => round($diffPercent, 2),
            'message'      => $won
                ? '🎯 Félicitations ! Votre estimation est dans la fourchette !'
                : "Pas tout à fait ! L'écart était de " . round($diffPercent, 1) . "% (tolérance : {$tolerance}%).",
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  MES JEUX (tableau de bord utilisateur)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Résumé de tous les jeux de l'utilisateur connecté.
     * GET /games/me
     */
    public function myGames(Request $request): JsonResponse
    {
        $user      = $request->user();
        $monthYear = now()->format('Y-m');

        return response()->json([
            'success' => true,
            'data'    => [
                'scratch_cards' => [
                    'this_month'    => ScratchCard::where('user_id', $user->id)->where('month_year', $monthYear)->count(),
                    'unscratched'   => ScratchCard::where('user_id', $user->id)->where('is_scratched', false)->count(),
                    'prizes_to_claim' => ScratchCard::where('user_id', $user->id)->where('is_scratched', true)->where('prize_claimed', false)->whereNotIn('prize_type', ['empty', 'message'])->count(),
                ],
                'wheel' => WheelConfig::where('is_active', true)->get()->map(fn($cfg) => [
                    'config_id'      => $cfg->id,
                    'name'           => $cfg->name,
                    'spins_used'     => WheelSpin::where('user_id', $user->id)->where('wheel_config_id', $cfg->id)->where('month_year', $monthYear)->count(),
                    'spins_per_month'=> $cfg->spins_per_month,
                ]),
                'defis' => [
                    'participated' => GameDefiParticipant::where('user_id', $user->id)->count(),
                    'won'          => GameDefiParticipant::where('user_id', $user->id)->where('is_winner', true)->count(),
                ],
                'quiz' => [
                    'played'    => QuizParticipation::where('user_id', $user->id)->count(),
                    'won'       => QuizParticipation::where('user_id', $user->id)->where('won', true)->count(),
                    'best_score'=> QuizParticipation::where('user_id', $user->id)->max('score_percent'),
                ],
                'battle' => [
                    'votes_cast' => BattleVote::where('user_id', $user->id)->count(),
                ],
                'juste_prix' => [
                    'participated' => JustePrixParticipation::where('user_id', $user->id)->count(),
                    'won'          => JustePrixParticipation::where('user_id', $user->id)->where('won', true)->count(),
                ],
            ],
        ]);
    }
}