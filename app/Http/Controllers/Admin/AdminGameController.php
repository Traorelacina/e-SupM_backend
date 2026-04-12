<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminGameController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function index(): JsonResponse { return response()->json(Game::withCount('participants')->latest()->paginate(20)); }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name'=>['required','string'],'type'=>['required','string'],'description'=>['nullable','string'],'is_open_to_all'=>['nullable','boolean'],'requires_purchase'=>['nullable','boolean'],'min_purchase_amount'=>['nullable','numeric'],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date'],'time_limit_seconds'=>['nullable','integer'],'loyalty_points_prize'=>['nullable','integer'],'prizes'=>['nullable']]);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('games','public');
        return response()->json(Game::create($data), 201);
    }

    public function show(int $id): JsonResponse { return response()->json(Game::with(['quizQuestions','wheelPrizes','battleCandidates'])->withCount('participants')->findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Game::findOrFail($id)->update($request->all()); return response()->json(['message'=>'Jeu mis à jour']); }
    public function destroy(int $id): JsonResponse { Game::findOrFail($id)->delete(); return response()->json(['message'=>'Jeu supprimé']); }
    public function activate(int $id): JsonResponse { Game::findOrFail($id)->update(['status'=>'active']); return response()->json(['message'=>'Jeu activé']); }
    public function close(int $id): JsonResponse { Game::findOrFail($id)->update(['status'=>'closed']); return response()->json(['message'=>'Jeu clôturé']); }
    public function participants(int $id): JsonResponse { return response()->json(Game::findOrFail($id)->participants()->with('user:id,name,email')->latest()->paginate(20)); }

    public function selectWinners(Request $request, int $id): JsonResponse
    {
        $request->validate(['count'=>['nullable','integer','min:1'],'participant_ids'=>['nullable','array']]);
        $game = Game::findOrFail($id);
        if ($request->participant_ids) {
            GameParticipant::whereIn('id', $request->participant_ids)->update(['is_winner'=>true]);
        } else {
            $count = $request->count ?? 1;
            GameParticipant::where('game_id', $id)->inRandomOrder()->take($count)->update(['is_winner'=>true]);
        }
        return response()->json(['message'=>'Gagnants sélectionnés']);
    }

    public function awardWinner(Request $request, int $id): JsonResponse
    {
        $request->validate(['participant_id'=>['required','exists:game_participants,id'],'prize'=>['required','string'],'points'=>['nullable','integer']]);
        $participant = GameParticipant::findOrFail($request->participant_id);
        $participant->update(['prize'=>$request->prize,'is_winner'=>true,'prize_claimed'=>false,'loyalty_points_won'=>$request->points??0]);
        if ($request->points) $this->loyaltyService->awardPoints($participant->user, $request->points, 'game_win', "Lot jeu: {$game->name}", null, $id);
        return response()->json(['message'=>'Lot attribué']);
    }
}
