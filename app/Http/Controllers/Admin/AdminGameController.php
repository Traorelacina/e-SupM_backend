<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BattleCandidate;
use App\Models\BattleContest;
use App\Models\BattleVote;
use App\Models\GameDefi;
use App\Models\GameDefiParticipant;
use App\Models\JustePrix;
use App\Models\JustePrixParticipation;
use App\Models\QuizSession;
use App\Models\ScratchCard;
use App\Models\WheelConfig;
use App\Models\WheelSpin;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGameController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    // ═══════════════════════════════════════════════════════════════
    //  TABLEAU DE BORD GLOBAL
    // ═══════════════════════════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats'   => [
                'defis'        => [
                    'total'    => GameDefi::count(),
                    'active'   => GameDefi::where('status', 'active')->count(),
                    'voting'   => GameDefi::where('status', 'voting')->count(),
                    'participants_this_week' => GameDefiParticipant::whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
                ],
                'scratch_cards'=> [
                    'issued'   => ScratchCard::count(),
                    'scratched'=> ScratchCard::where('is_scratched', true)->count(),
                    'pending'  => ScratchCard::where('is_scratched', false)->count(),
                    'prizes_unclaimed' => ScratchCard::where('is_scratched', true)->where('prize_claimed', false)->whereNotIn('prize_type', ['empty', 'message'])->count(),
                ],
                'wheel'        => [
                    'spins_this_month' => WheelSpin::whereRaw("month_year = ?", [now()->format('Y-m')])->count(),
                    'prizes_unclaimed' => WheelSpin::where('prize_claimed', false)->whereNotIn('prize_type', ['empty'])->count(),
                    'configs'  => WheelConfig::where('is_active', true)->count(),
                ],
                'quiz'         => [
                    'sessions'       => QuizSession::count(),
                    'active'         => QuizSession::where('status', 'active')->count(),
                    'participations' => \App\Models\QuizParticipation::count(),
                    'winners'        => \App\Models\QuizParticipation::where('won', true)->count(),
                ],
                'battle'       => [
                    'total'       => BattleContest::count(),
                    'active'      => BattleContest::where('status', 'active')->count(),
                    'votes_today' => BattleVote::whereDate('created_at', today())->count(),
                ],
                'juste_prix'   => [
                    'sessions'   => JustePrix::count(),
                    'participations' => JustePrixParticipation::count(),
                    'winners'    => JustePrixParticipation::where('won', true)->count(),
                ],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  DÉFIS
    // ═══════════════════════════════════════════════════════════════

    public function defiIndex(): JsonResponse
    {
        $defis = GameDefi::withCount(['participants', 'participants as selected_count' => fn($q) => $q->where('is_selected', true)])
            ->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => $defis]);
    }

    public function defiStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'challenge_text'      => ['required', 'string'],
            'challenge_video_url' => ['nullable', 'url'],
            'starts_at'           => ['required', 'date'],
            'ends_at'             => ['required', 'date', 'after:starts_at'],
            'voting_ends_at'      => ['required', 'date', 'after:ends_at'],
            'prize_description'   => ['nullable', 'string'],
            'loyalty_points_prize'=> ['nullable', 'integer'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('games/defis', 'public');
        }
        if ($request->hasFile('prize_image')) {
            $data['prize_image'] = $request->file('prize_image')->store('games/prizes', 'public');
        }

        $data['status'] = 'draft';

        return response()->json(['success' => true, 'data' => GameDefi::create($data)], 201);
    }

    public function defiShow(int $id): JsonResponse
    {
        $defi = GameDefi::with([
            'participants' => fn($q) => $q->with('user:id,name,email,avatar')->withCount('votes')->orderByDesc('votes_count'),
            'winner.user:id,name,email',
        ])->withCount('participants')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $defi]);
    }

    public function defiUpdate(Request $request, int $id): JsonResponse
    {
        $defi = GameDefi::findOrFail($id);
        $defi->update($request->except(['image', 'prize_image']));

        if ($request->hasFile('image')) {
            $defi->update(['image' => $request->file('image')->store('games/defis', 'public')]);
        }

        return response()->json(['success' => true, 'message' => 'Défi mis à jour', 'data' => $defi->fresh()]);
    }

    public function defiDestroy(int $id): JsonResponse
    {
        GameDefi::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Défi supprimé']);
    }

    /** Activer / passer en vote / clôturer */
    public function defiSetStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:draft,active,voting,closed']]);
        GameDefi::findOrFail($id)->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Statut défi mis à jour']);
    }

    /** Sélectionner des participants pour la phase de vote */
    public function defiSelectParticipants(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
        ]);

        GameDefiParticipant::where('game_defi_id', $id)->update(['is_selected' => false]);
        GameDefiParticipant::whereIn('id', $request->participant_ids)
            ->where('game_defi_id', $id)
            ->update(['is_selected' => true]);

        return response()->json(['success' => true, 'message' => 'Participants sélectionnés pour le vote']);
    }

    /** Désigner le gagnant et distribuer les récompenses */
    public function defiAwardWinner(Request $request, int $id): JsonResponse
    {
        $request->validate(['participant_id' => ['required', 'exists:game_defi_participants,id']]);

        DB::transaction(function () use ($id, $request) {
            $defi        = GameDefi::findOrFail($id);
            $participant = GameDefiParticipant::with('user')->findOrFail($request->participant_id);

            $participant->update(['is_winner' => true, 'prize_claimed' => false, 'earned_at' => now()]);
            $defi->update(['winner_participant_id' => $participant->id, 'status' => 'closed']);

            if ($defi->loyalty_points_prize && $participant->user) {
                $this->loyaltyService->awardPoints(
                    $participant->user,
                    $defi->loyalty_points_prize,
                    'game_win',
                    "Gagnant défi : {$defi->title}",
                    null,
                    $id
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Gagnant désigné et récompenses distribuées']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  CARTE À GRATTER
    // ═══════════════════════════════════════════════════════════════

    public function scratchIndex(Request $request): JsonResponse
    {
        $q = ScratchCard::with('user:id,name,email');
        if ($request->trigger_type) $q->where('trigger_type', $request->trigger_type);
        if ($request->is_scratched !== null) $q->where('is_scratched', $request->boolean('is_scratched'));
        if ($request->month_year)   $q->where('month_year', $request->month_year);
        return response()->json(['success' => true, 'data' => $q->latest()->paginate(20)]);
    }

    public function scratchStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'by_type'  => ScratchCard::selectRaw('prize_type, COUNT(*) as count, SUM(CASE WHEN prize_claimed THEN 1 ELSE 0 END) as claimed')->where('is_scratched', true)->groupBy('prize_type')->get(),
                'monthly'  => ScratchCard::selectRaw("month_year, COUNT(*) as issued, SUM(is_scratched) as scratched")->groupBy('month_year')->orderByDesc('month_year')->limit(6)->get(),
                'totals'   => [
                    'issued'            => ScratchCard::count(),
                    'scratched'         => ScratchCard::where('is_scratched', true)->count(),
                    'prizes_unclaimed'  => ScratchCard::where('is_scratched', true)->where('prize_claimed', false)->whereNotIn('prize_type', ['empty', 'message'])->count(),
                ],
            ],
        ]);
    }

    /** Déclencher manuellement une carte pour un utilisateur */
    public function scratchTriggerManual(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'      => ['required', 'exists:users,id'],
            'trigger_type' => ['required', 'in:purchase,charity,manual'],
            'reason'       => ['nullable', 'string'],
        ]);

        $monthYear = now()->format('Y-m');
        $prize     = ScratchCard::drawPrize($request->trigger_type);

        $card = ScratchCard::create([
            'user_id'         => $request->user_id,
            'month_year'      => $monthYear,
            'trigger_type'    => $request->trigger_type,
            'trigger_amount'  => 0,
            'prize_type'      => $prize['type'],
            'prize_label'     => $prize['label'],
            'prize_value'     => $prize['value'],
            'prize_description'=> $request->reason ?? 'Attribution manuelle par admin',
            'expires_at'      => now()->endOfMonth(),
        ]);

        return response()->json(['success' => true, 'message' => 'Carte à gratter créée', 'data' => $card], 201);
    }

    /** Marquer un lot comme réclamé */
    public function scratchClaimPrize(int $id): JsonResponse
    {
        $card = ScratchCard::findOrFail($id);
        $card->update(['prize_claimed' => true, 'prize_claimed_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Lot marqué comme réclamé']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  ROUE E-SUP'M
    // ═══════════════════════════════════════════════════════════════

    public function wheelConfigIndex(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => WheelConfig::all()]);
    }

    public function wheelConfigStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string'],
            'wheel_type'          => ['required', 'in:wholesale,standard'],
            'min_purchase_amount' => ['required', 'numeric'],
            'spins_per_month'     => ['required', 'integer', 'min:1', 'max:5'],
            'prizes'              => ['nullable', 'array'],
            'is_active'           => ['nullable', 'boolean'],
        ]);

        if (empty($data['prizes'])) {
            $data['prizes'] = WheelConfig::defaultPrizes($data['wheel_type']);
        }

        return response()->json(['success' => true, 'data' => WheelConfig::create($data)], 201);
    }

    public function wheelConfigUpdate(Request $request, int $id): JsonResponse
    {
        WheelConfig::findOrFail($id)->update($request->all());
        return response()->json(['success' => true, 'message' => 'Configuration roue mise à jour']);
    }

    public function wheelSpinsIndex(Request $request): JsonResponse
    {
        $q = WheelSpin::with(['user:id,name,email', 'wheelConfig:id,name,wheel_type']);
        if ($request->wheel_type) $q->whereHas('wheelConfig', fn($wq) => $wq->where('wheel_type', $request->wheel_type));
        if ($request->month_year) $q->where('month_year', $request->month_year);
        if ($request->prize_claimed !== null) $q->where('prize_claimed', $request->boolean('prize_claimed'));
        return response()->json(['success' => true, 'data' => $q->latest()->paginate(20)]);
    }

    /** Déclencher manuellement un tour de roue pour un utilisateur */
    public function wheelSpinManual(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'         => ['required', 'exists:users,id'],
            'wheel_config_id' => ['required', 'exists:wheel_configs,id'],
        ]);

        $config    = WheelConfig::findOrFail($request->wheel_config_id);
        $prizes    = $config->prizes;
        $totalW    = array_sum(array_column($prizes, 'weight'));
        $rand      = random_int(1, $totalW);
        $cum       = 0;
        $prize     = end($prizes);

        foreach ($prizes as $p) {
            $cum += $p['weight'];
            if ($rand <= $cum) { $prize = $p; break; }
        }

        $monthYear  = now()->format('Y-m');
        $spinNumber = WheelSpin::where('user_id', $request->user_id)->where('wheel_config_id', $config->id)->where('month_year', $monthYear)->count() + 1;

        $spin = WheelSpin::create([
            'user_id'         => $request->user_id,
            'wheel_config_id' => $config->id,
            'month_year'      => $monthYear,
            'spin_number'     => $spinNumber,
            'prize_label'     => $prize['label'],
            'prize_type'      => $prize['type'],
            'prize_value'     => $prize['value'] ?? 0,
            'triggered_by'    => 'manual_admin',
        ]);

        if (in_array($prize['type'], ['points']) && $prize['value'] > 0) {
            $user = \App\Models\User::find($request->user_id);
            $this->loyaltyService->awardPoints($user, $prize['value'], 'game_win', "Lot roue e-Sup'M : {$prize['label']}");
        }

        return response()->json(['success' => true, 'message' => 'Tour de roue effectué', 'data' => ['spin' => $spin, 'prize' => $prize]]);
    }

    public function wheelClaimPrize(int $id): JsonResponse
    {
        WheelSpin::findOrFail($id)->update(['prize_claimed' => true, 'prize_claimed_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Lot roue marqué comme réclamé']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  QUIZ
    // ═══════════════════════════════════════════════════════════════

    public function quizIndex(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => QuizSession::withCount(['questions', 'participations', 'participations as winners_count' => fn($q) => $q->where('won', true)])
                ->latest()->paginate(20),
        ]);
    }

    public function quizStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => ['required', 'string'],
            'theme'               => ['required', 'string'],
            'description'         => ['nullable', 'string'],
            'starts_at'           => ['required', 'date'],
            'ends_at'             => ['required', 'date', 'after:starts_at'],
            'time_limit_seconds'  => ['required', 'integer', 'min:10'],
            'prize_description'   => ['nullable', 'string'],
            'loyalty_points_prize'=> ['nullable', 'integer'],
            'min_score_to_win'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'retry_delay_hours'   => ['nullable', 'integer', 'min:1'],
        ]);
        $data['status'] = 'draft';

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('games/quiz', 'public');
        }

        return response()->json(['success' => true, 'data' => QuizSession::create($data)], 201);
    }

    public function quizShow(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => QuizSession::with(['questions.options', 'participations' => fn($q) => $q->with('user:id,name,email')->latest()->take(10)])
                ->withCount(['questions', 'participations', 'participations as winners_count' => fn($q) => $q->where('won', true)])
                ->findOrFail($id),
        ]);
    }

    public function quizUpdate(Request $request, int $id): JsonResponse
    {
        QuizSession::findOrFail($id)->update($request->except(['image']));
        return response()->json(['success' => true, 'message' => 'Quiz mis à jour']);
    }

    public function quizDestroy(int $id): JsonResponse
    {
        QuizSession::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Quiz supprimé']);
    }

    public function quizSetStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:draft,active,closed']]);
        QuizSession::findOrFail($id)->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Statut quiz mis à jour']);
    }

    // ─── Questions ─────────────────────────────────────────────────

    public function quizAddQuestion(Request $request, int $id): JsonResponse
    {
        $session = QuizSession::findOrFail($id);
        $data    = $request->validate([
            'question_text'  => ['required', 'string'],
            'type'           => ['required', 'in:multiple_choice,true_false,text_input'],
            'points'         => ['nullable', 'integer', 'min:1'],
            'explanation'    => ['nullable', 'string'],
            'options'        => ['required_if:type,multiple_choice,true_false', 'array', 'min:2'],
            'options.*.text' => ['required_with:options', 'string'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
        ]);

        $question = $session->questions()->create([
            'question_text' => $data['question_text'],
            'type'          => $data['type'],
            'points'        => $data['points'] ?? 10,
            'explanation'   => $data['explanation'] ?? null,
            'order'         => $session->questions()->max('order') + 1,
        ]);

        if (!empty($data['options'])) {
            foreach ($data['options'] as $index => $option) {
                $question->options()->create([
                    'option_text' => $option['text'],
                    'is_correct'  => $option['is_correct'],
                    'order'       => $index + 1,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $question->load('options')], 201);
    }

    public function quizUpdateQuestion(Request $request, int $id, int $questionId): JsonResponse
    {
        $question = \App\Models\QuizQuestion::where('quiz_session_id', $id)->findOrFail($questionId);
        $question->update($request->only(['question_text', 'type', 'points', 'explanation', 'order']));

        if ($request->has('options')) {
            $question->options()->delete();
            foreach ($request->options as $index => $option) {
                $question->options()->create([
                    'option_text' => $option['text'],
                    'is_correct'  => $option['is_correct'],
                    'order'       => $index + 1,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Question mise à jour', 'data' => $question->load('options')]);
    }

    public function quizDeleteQuestion(int $id, int $questionId): JsonResponse
    {
        \App\Models\QuizQuestion::where('quiz_session_id', $id)->findOrFail($questionId)->delete();
        return response()->json(['success' => true, 'message' => 'Question supprimée']);
    }

    public function quizParticipations(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => \App\Models\QuizParticipation::where('quiz_session_id', $id)
                ->with('user:id,name,email')
                ->orderByDesc('score')
                ->paginate(20),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  BATTLE (VOTE)
    // ═══════════════════════════════════════════════════════════════

    public function battleIndex(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => BattleContest::with(['candidates' => fn($q) => $q->orderByDesc('votes_count')])
                ->withCount('votes')
                ->latest()->paginate(20),
        ]);
    }

    public function battleStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => ['required', 'string'],
            'type'                => ['required', 'in:promo,product,team'],
            'description'         => ['nullable', 'string'],
            'starts_at'           => ['required', 'date'],
            'ends_at'             => ['required', 'date', 'after:starts_at'],
            'prize_description'   => ['nullable', 'string'],
            'loyalty_points_prize'=> ['nullable', 'integer'],
            'candidates'          => ['required', 'array', 'min:2'],
            'candidates.*.name'   => ['required', 'string'],
            'candidates.*.description' => ['nullable', 'string'],
        ]);

        $contest = DB::transaction(function () use ($data, $request) {
            $contest = BattleContest::create([
                'title'                => $data['title'],
                'type'                 => $data['type'],
                'description'          => $data['description'] ?? null,
                'starts_at'            => $data['starts_at'],
                'ends_at'              => $data['ends_at'],
                'prize_description'    => $data['prize_description'] ?? null,
                'loyalty_points_prize' => $data['loyalty_points_prize'] ?? 0,
                'status'               => 'draft',
            ]);

            foreach ($data['candidates'] as $index => $candidate) {
                $candidateData = [
                    'name'        => $candidate['name'],
                    'description' => $candidate['description'] ?? null,
                    'order'       => $index + 1,
                ];
                if ($request->hasFile("candidates.{$index}.image")) {
                    $candidateData['image'] = $request->file("candidates.{$index}.image")->store('games/battle', 'public');
                }
                $contest->candidates()->create($candidateData);
            }

            return $contest;
        });

        return response()->json(['success' => true, 'data' => $contest->load('candidates')], 201);
    }

    public function battleShow(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => BattleContest::with(['candidates' => fn($q) => $q->orderByDesc('votes_count'), 'winner'])
                ->withCount('votes')
                ->findOrFail($id),
        ]);
    }

    public function battleUpdate(Request $request, int $id): JsonResponse
    {
        BattleContest::findOrFail($id)->update($request->only(['title', 'type', 'description', 'starts_at', 'ends_at', 'prize_description', 'loyalty_points_prize']));
        return response()->json(['success' => true, 'message' => 'Battle mis à jour']);
    }

    public function battleDestroy(int $id): JsonResponse
    {
        BattleContest::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Battle supprimé']);
    }

    public function battleSetStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:draft,active,closed']]);
        BattleContest::findOrFail($id)->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Statut battle mis à jour']);
    }

    /** Clôturer et calculer le gagnant automatiquement */
    public function battleClose(int $id): JsonResponse
    {
        $contest = BattleContest::findOrFail($id);
        $winner  = $contest->computeWinner();

        if ($winner && $contest->loyalty_points_prize) {
            BattleVote::where('candidate_id', $winner->id)
                ->with('user')
                ->get()
                ->each(function ($vote) use ($contest) {
                    if ($vote->user) {
                        $this->loyaltyService->awardPoints($vote->user, $contest->loyalty_points_prize, 'game_win', "Vote gagnant battle : {$contest->title}");
                    }
                });
        }

        return response()->json([
            'success' => true,
            'message' => 'Battle clôturé',
            'winner'  => $winner,
        ]);
    }

    public function battleAddCandidate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $contest   = BattleContest::findOrFail($id);
        $candidate = $contest->candidates()->create([
            ...$data,
            'image' => $request->hasFile('image') ? $request->file('image')->store('games/battle', 'public') : null,
            'order' => $contest->candidates()->max('order') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $candidate], 201);
    }

    public function battleVotes(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => BattleVote::where('battle_contest_id', $id)
                ->with(['user:id,name,email', 'candidate:id,name'])
                ->latest()->paginate(20),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  JUSTE PRIX
    // ═══════════════════════════════════════════════════════════════

    public function justePrixIndex(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => JustePrix::withCount(['participations', 'participations as winners_count' => fn($q) => $q->where('won', true)])
                ->latest()->paginate(20),
        ]);
    }

    public function justePrixStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => ['required', 'string'],
            'starts_at'           => ['required', 'date'],
            'ends_at'             => ['required', 'date', 'after:starts_at'],
            'prize_description'   => ['nullable', 'string'],
            'loyalty_points_prize'=> ['nullable', 'integer'],
            'tolerance_percent'   => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $data['status'] = 'draft';

        return response()->json(['success' => true, 'data' => JustePrix::create($data)], 201);
    }

    public function justePrixShow(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => JustePrix::with(['participations' => fn($q) => $q->with(['user:id,name,email', 'product:id,name,price'])->latest()->take(20)])
                ->withCount(['participations', 'participations as winners_count' => fn($q) => $q->where('won', true)])
                ->findOrFail($id),
        ]);
    }

    public function justePrixUpdate(Request $request, int $id): JsonResponse
    {
        JustePrix::findOrFail($id)->update($request->only(['title', 'starts_at', 'ends_at', 'prize_description', 'loyalty_points_prize', 'tolerance_percent', 'status']));
        return response()->json(['success' => true, 'message' => 'Juste Prix mis à jour']);
    }

    public function justePrixSetStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:draft,active,closed']]);
        JustePrix::findOrFail($id)->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Statut Juste Prix mis à jour']);
    }

    public function justePrixParticipations(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => JustePrixParticipation::where('juste_prix_id', $id)
                ->with(['user:id,name,email', 'product:id,name,price,primary_image'])
                ->latest()->paginate(20),
        ]);
    }

    public function justePrixAwardWinner(Request $request, int $participationId): JsonResponse
    {
        $participation = JustePrixParticipation::with(['user', 'game'])->findOrFail($participationId);

        DB::transaction(function () use ($participation) {
            $participation->update(['won' => true]);

            if ($participation->game->loyalty_points_prize && $participation->user) {
                $this->loyaltyService->awardPoints(
                    $participation->user,
                    $participation->game->loyalty_points_prize,
                    'game_win',
                    "Gagnant Juste Prix",
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Récompense attribuée']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  AUTOMATISATIONS PLANIFIÉES (appelables depuis un Scheduler)
    // ═══════════════════════════════════════════════════════════════

    /**
     * À appeler chaque jeudi – crée automatiquement un nouveau défi si aucun n'est actif.
     */
    public function autoScheduleDefi(): void
    {
        if (!GameDefi::where('status', 'active')->exists()) {
            // Possibilité de créer un défi type depuis la config ou un template
        }
    }

    /**
     * À appeler chaque mardi – active le quiz de la semaine.
     */
    public function autoActivateQuiz(): void
    {
        QuizSession::where('status', 'draft')
            ->whereDate('starts_at', today())
            ->update(['status' => 'active']);
    }

    /**
     * À appeler chaque mercredi – active le battle de la semaine.
     */
    public function autoActivateBattle(): void
    {
        BattleContest::where('status', 'draft')
            ->whereDate('starts_at', today())
            ->update(['status' => 'active']);
    }

    /**
     * Clôturer automatiquement les jeux expirés.
     */
    public function autoCloseExpiredGames(): void
    {
        GameDefi::where('status', 'active')->where('ends_at', '<', now())->update(['status' => 'voting']);
        GameDefi::where('status', 'voting')->where('voting_ends_at', '<', now())->update(['status' => 'closed']);
        QuizSession::where('status', 'active')->where('ends_at', '<', now())->update(['status' => 'closed']);
        BattleContest::where('status', 'active')->where('ends_at', '<', now())->each(fn($b) => $b->computeWinner());
        JustePrix::where('status', 'active')->where('ends_at', '<', now())->update(['status' => 'closed']);
    }
}