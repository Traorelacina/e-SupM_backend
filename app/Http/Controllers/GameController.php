<?php

namespace App\Http\Controllers;

use App\Models\BattleCandidate;
use App\Models\BattleVote;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\Product;
use App\Models\WheelPrize;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function index(): JsonResponse
    {
        $games = Game::active()->with(['participants' => fn($q) => $q->where('is_winner', true)->latest()->take(3)->with('user')])->get();
        return response()->json($games);
    }

    public function show(int $id): JsonResponse
    {
        $game = Game::with(['quizQuestions', 'wheelPrizes', 'battleCandidates'])->findOrFail($id);
        // Hide correct answers
        if ($game->type === 'quiz') {
            $game->quizQuestions->makeHidden('correct_answer');
        }
        return response()->json($game);
    }

    public function winners(): JsonResponse
    {
        $winners = GameParticipant::where('is_winner', true)
            ->with(['user:id,name,avatar', 'game:id,name,type'])
            ->latest()
            ->take(20)
            ->get();
        return response()->json($winners);
    }

    // ========================
    // REGISTER
    // ========================
    public function register(Request $request, int $id): JsonResponse
    {
        $game = Game::active()->findOrFail($id);
        $user = $request->user();

        if (!$game->requires_registration) {
            return response()->json(['message' => 'Ce jeu ne nécessite pas d\'inscription'], 422);
        }

        if ($game->max_participants && $game->participants()->count() >= $game->max_participants) {
            return response()->json(['message' => 'Le nombre maximum de participants est atteint'], 422);
        }

        // Check eligibility
        $this->checkEligibility($game, $user);

        $existing = $game->participants()->where('user_id', $user->id)->first();
        if ($existing) return response()->json(['message' => 'Vous êtes déjà inscrit à ce jeu']);

        GameParticipant::create([
            'game_id'          => $game->id,
            'user_id'          => $user->id,
            'participated_at'  => now(),
        ]);

        return response()->json(['message' => 'Inscription réussie !'], 201);
    }

    // ========================
    // PARTICIPATE (generic)
    // ========================
    public function participate(Request $request, int $id): JsonResponse
    {
        $game = Game::active()->findOrFail($id);
        $user = $request->user();

        $this->checkEligibility($game, $user);
        $this->checkCooldown($game, $user);

        return match($game->type) {
            'defi'        => $this->participateDefi($request, $game, $user),
            'juste_prix'  => $this->participateJustePrix($request, $game, $user),
            default       => response()->json(['message' => 'Utilisez les endpoints spécifiques'], 422),
        };
    }

    // ========================
    // SCRATCH CARD
    // ========================
    public function revealScratchCard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->loyaltyService->checkScratchCardEligibility($user)) {
            return response()->json(['message' => 'Vous n\'êtes pas éligible à la carte à gratter ce mois-ci. Minimum 15 000 FCFA d\'achats requis.'], 403);
        }

        $game = Game::where('type', 'carte_gratter')->active()->first();
        if (!$game) return response()->json(['message' => 'Aucune carte à gratter disponible actuellement'], 404);

        // Check if already used this month
        $alreadyUsed = GameParticipant::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->exists();
        if ($alreadyUsed) return response()->json(['message' => 'Vous avez déjà utilisé votre carte à gratter ce mois-ci'], 422);

        return DB::transaction(function () use ($game, $user) {
            $prize = $this->drawPrize($game);

            $participant = GameParticipant::create([
                'game_id'             => $game->id,
                'user_id'             => $user->id,
                'is_winner'           => $prize['type'] !== 'retry',
                'prize'               => $prize['label'],
                'loyalty_points_won'  => $prize['points'] ?? 0,
                'metadata'            => $prize,
                'participated_at'     => now(),
            ]);

            if (($prize['points'] ?? 0) > 0) {
                $this->loyaltyService->awardPoints($user, $prize['points'], 'game_win', "Carte à gratter: {$prize['label']}", null, $game->id);
            }

            return response()->json([
                'prize'       => $prize,
                'is_winner'   => $participant->is_winner,
                'message'     => $participant->is_winner ? "Félicitations ! Vous avez gagné : {$prize['label']}" : "Retentez votre chance le mois prochain !",
            ]);
        });
    }

    // ========================
    // SPIN WHEEL
    // ========================
    public function spinWheel(Request $request): JsonResponse
    {
        $request->validate(['wheel_number' => ['required', 'integer', 'in:1,2']]);
        $user = $request->user();
        $wheelNum = $request->wheel_number;

        if (!$this->loyaltyService->checkWheelEligibility($user, $wheelNum)) {
            $required = $wheelNum === 1 ? '50 000' : '15 000';
            return response()->json(['message' => "Minimum {$required} FCFA d'achats ce mois-ci requis"], 403);
        }

        $game = Game::where('type', 'roue')->active()->get()->get($wheelNum - 1);
        if (!$game) return response()->json(['message' => 'Roue non disponible'], 404);

        // Check if already spun twice this month (wheel 1 = 2x/month)
        $maxSpins = $wheelNum === 1 ? 2 : 1;
        $spinsThisMonth = GameParticipant::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        if ($spinsThisMonth >= $maxSpins) {
            return response()->json(['message' => 'Vous avez épuisé vos tours pour ce mois-ci'], 422);
        }

        return DB::transaction(function () use ($game, $user) {
            $prize = $this->drawWheelPrize($game);
            $participant = GameParticipant::create([
                'game_id'            => $game->id,
                'user_id'            => $user->id,
                'is_winner'          => $prize['type'] !== 'retry',
                'prize'              => $prize['label'],
                'loyalty_points_won' => $prize['points'] ?? 0,
                'metadata'           => $prize,
                'participated_at'    => now(),
            ]);
            if (($prize['points'] ?? 0) > 0) {
                $this->loyaltyService->awardPoints($user, $prize['points'], 'game_win', "Roue e-Sup'M: {$prize['label']}", null, $game->id);
            }
            return response()->json(['prize' => $prize, 'segment_index' => $prize['segment_index']]);
        });
    }

    // ========================
    // QUIZ
    // ========================
    public function answerQuiz(Request $request): JsonResponse
    {
        $request->validate([
            'game_id'   => ['required', 'exists:games,id'],
            'answers'   => ['required', 'array'],
            'time_taken'=> ['nullable', 'integer'],
        ]);

        $game = Game::active()->where('type', 'quiz')->findOrFail($request->game_id);
        $user = $request->user();

        $this->checkCooldown($game, $user);

        $questions = $game->quizQuestions()->get();
        $score = 0;
        $correctCount = 0;

        foreach ($request->answers as $qId => $answer) {
            $question = $questions->where('id', $qId)->first();
            if ($question && $question->correct_answer === $answer) {
                $score += $question->points;
                $correctCount++;
            }
        }

        $allCorrect  = $correctCount === $questions->count();
        $pointsWon   = $allCorrect ? ($game->loyalty_points_prize ?? 0) : 0;

        $participant = GameParticipant::create([
            'game_id'            => $game->id,
            'user_id'            => $user->id,
            'answer'             => $request->answers,
            'score'              => $score,
            'is_winner'          => $allCorrect,
            'prize'              => $allCorrect ? ($game->prizes[0] ?? 'Points de fidélité') : null,
            'loyalty_points_won' => $pointsWon,
            'metadata'           => ['correct_count' => $correctCount, 'total' => $questions->count(), 'time_taken' => $request->time_taken],
            'participated_at'    => now(),
        ]);

        if ($pointsWon > 0) {
            $this->loyaltyService->awardPoints($user, $pointsWon, 'game_win', "Quiz e-Sup'M gagné !", null, $game->id);
        }

        return response()->json([
            'score'          => $score,
            'correct_count'  => $correctCount,
            'total_questions'=> $questions->count(),
            'is_winner'      => $allCorrect,
            'prize'          => $participant->prize,
            'points_won'     => $pointsWon,
            'message'        => $allCorrect ? "Félicitations, score parfait !" : "Retentez dans 3 jours !",
        ]);
    }

    // ========================
    // JUSTE PRIX
    // ========================
    public function guessPrix(Request $request): JsonResponse
    {
        $request->validate([
            'game_id'   => ['required', 'exists:games,id'],
            'product_id'=> ['required', 'exists:products,id'],
            'guessed_price' => ['required', 'numeric', 'min:0'],
        ]);

        $game    = Game::active()->where('type', 'juste_prix')->findOrFail($request->game_id);
        $user    = $request->user();
        $product = Product::findOrFail($request->product_id);

        $this->checkCooldown($game, $user);

        $realPrice   = $product->price;
        $guessed     = $request->guessed_price;
        $difference  = abs($realPrice - $guessed);
        $tolerance   = $realPrice * 0.05; // 5% tolerance
        $isWinner    = $difference <= $tolerance;
        $pointsWon   = $isWinner ? ($game->loyalty_points_prize ?? 50) : 0;

        $participant = GameParticipant::create([
            'game_id'            => $game->id,
            'user_id'            => $user->id,
            'answer'             => ['guessed' => $guessed, 'real' => $realPrice],
            'score'              => $isWinner ? 100 : max(0, 100 - (int)(($difference / $realPrice) * 100)),
            'is_winner'          => $isWinner,
            'prize'              => $isWinner ? ($game->prizes[0] ?? null) : null,
            'loyalty_points_won' => $pointsWon,
            'participated_at'    => now(),
        ]);

        if ($pointsWon > 0) {
            $this->loyaltyService->awardPoints($user, $pointsWon, 'game_win', "Juste Prix gagné !", null, $game->id);
        }

        return response()->json([
            'guessed_price' => $guessed,
            'real_price'    => $realPrice,
            'difference'    => $difference,
            'is_winner'     => $isWinner,
            'prize'         => $participant->prize,
            'points_won'    => $pointsWon,
            'message'       => $isWinner ? "🎉 Bravo, c'est le bon prix !" : "Le prix réel était {$realPrice} FCFA. Réessayez dans 3 jours !",
        ]);
    }

    // ========================
    // BATTLE VOTE
    // ========================
    public function vote(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'candidate_id' => ['required', 'exists:battle_candidates,id'],
            'battle_type'  => ['required', 'in:promo,rayon,team'],
        ]);

        $game      = Game::active()->where('type', 'battle')->findOrFail($id);
        $user      = $request->user();
        $candidate = BattleCandidate::where('game_id', $game->id)->findOrFail($request->candidate_id);

        // Check already voted for this battle type
        $alreadyVoted = BattleVote::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->where('battle_type', $request->battle_type)
            ->exists();

        if ($alreadyVoted) return response()->json(['message' => 'Vous avez déjà voté dans cette catégorie'], 422);

        DB::transaction(function () use ($game, $user, $candidate, $request) {
            BattleVote::create([
                'game_id'              => $game->id,
                'user_id'              => $user->id,
                'battle_candidate_id'  => $candidate->id,
                'battle_type'          => $request->battle_type,
            ]);
            $candidate->increment('votes_count');
        });

        return response()->json(['message' => 'Vote enregistré !', 'candidate' => $candidate->fresh()]);
    }

    public function myResult(Request $request, int $id): JsonResponse
    {
        $participation = GameParticipant::where('game_id', $id)->where('user_id', $request->user()->id)->latest()->first();
        return response()->json($participation);
    }

    public function myParticipations(Request $request): JsonResponse
    {
        $participations = $request->user()->gameParticipations()->with('game')->latest()->paginate(20);
        return response()->json($participations);
    }

    // ========================
    // PRIVATE HELPERS
    // ========================
    private function checkEligibility(Game $game, $user): void
    {
        if ($game->requires_purchase) {
            $monthlySpend = $user->orders()
                ->where('payment_status', 'paid')
                ->whereMonth('created_at', now()->month)
                ->sum('total');
            if ($monthlySpend < ($game->min_purchase_amount ?? 0)) {
                abort(403, "Minimum {$game->min_purchase_amount} FCFA d'achats ce mois requis.");
            }
        }
    }

    private function checkCooldown(Game $game, $user): void
    {
        if (!$game->participation_cooldown_days) return;
        $lastPlay = GameParticipant::where('game_id', $game->id)->where('user_id', $user->id)->latest('participated_at')->first();
        if ($lastPlay && $lastPlay->participated_at->addDays($game->participation_cooldown_days)->isFuture()) {
            $nextPlay = $lastPlay->participated_at->addDays($game->participation_cooldown_days);
            abort(422, "Vous pouvez rejouer le {$nextPlay->format('d/m/Y à H:i')}");
        }
    }

    private function drawPrize(Game $game): array
    {
        $prizes = json_decode($game->prizes ?? '[]', true);
        if (empty($prizes)) return ['type' => 'retry', 'label' => 'Retentez votre chance !', 'points' => 0];
        $rand = rand(1, 100);
        $cumulative = 0;
        foreach ($prizes as $prize) {
            $cumulative += ($prize['probability'] ?? 10);
            if ($rand <= $cumulative) return $prize;
        }
        return ['type' => 'retry', 'label' => 'Retentez votre chance !', 'points' => 0];
    }

    private function drawWheelPrize(Game $game): array
    {
        $prizes = $game->wheelPrizes()->get();
        if ($prizes->isEmpty()) return ['type' => 'retry', 'label' => 'Retentez', 'points' => 0, 'segment_index' => 0];
        $rand = rand(1, 100);
        $cumulative = 0;
        foreach ($prizes as $idx => $prize) {
            $cumulative += $prize->probability;
            if ($rand <= $cumulative) {
                return [...$prize->toArray(), 'segment_index' => $idx];
            }
        }
        return [...$prizes->last()->toArray(), 'segment_index' => $prizes->count() - 1];
    }

    private function participateDefi(Request $request, Game $game, $user): JsonResponse
    {
        GameParticipant::create([
            'game_id'         => $game->id,
            'user_id'         => $user->id,
            'metadata'        => $request->only(['video_url', 'description']),
            'participated_at' => now(),
        ]);
        return response()->json(['message' => 'Participation au défi enregistrée ! En attente de validation.']);
    }

    private function participateJustePrix(Request $request, Game $game, $user): JsonResponse
    {
        return $this->guessPrix($request);
    }
}
