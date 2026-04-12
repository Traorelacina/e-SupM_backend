<?php
namespace App\Http\Controllers;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoyaltyController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user     = $request->user();
        $progress = $this->loyaltyService->getLevelProgress($user);
        $badges   = $user->badges()->orderByPivot('earned_at', 'desc')->get();
        $recentTx = $user->loyaltyTransactions()->latest()->take(10)->get();

        return response()->json([
            'points'   => $user->loyalty_points,
            'level'    => $user->loyalty_level,
            'progress' => $progress,
            'badges'   => $badges,
            'recent_transactions' => $recentTx,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $request->user()->loyaltyTransactions()->latest()->paginate(20);
        return response()->json($transactions);
    }

    public function badges(Request $request): JsonResponse
    {
        $all    = \App\Models\Badge::where('is_active', true)->get();
        $earned = $request->user()->badges()->pluck('badge_id');
        return response()->json($all->map(fn($b) => [...$b->toArray(), 'earned' => $earned->contains($b->id)]));
    }

    public function redeem(Request $request): JsonResponse
    {
        $request->validate([
            'points' => ['required', 'integer', 'min:100'],
            'type'   => ['required', 'in:discount,product,gift'],
        ]);
        $user = $request->user();
        if ($user->loyalty_points < $request->points) {
            return response()->json(['message' => 'Points insuffisants'], 422);
        }
        $user->spendLoyaltyPoints($request->points, "Échange de points: {$request->type}");
        $discountValue = $request->points / 100; // 100 pts = 1 FCFA
        return response()->json(['message' => 'Points échangés !', 'discount_value' => $discountValue, 'remaining_points' => $user->fresh()->loyalty_points]);
    }

    public function leaderboard(): JsonResponse
    {
        $top = \App\Models\User::where('role', 'client')
            ->orderByDesc('total_points_earned')
            ->select('id','name','avatar','loyalty_level','total_points_earned')
            ->take(20)->get();
        return response()->json($top);
    }
}
