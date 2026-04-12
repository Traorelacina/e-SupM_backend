<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminBadgeController extends Controller
{
    public function index(): JsonResponse { return response()->json(Badge::withCount('users')->get()); }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name'=>['required','string'],'description'=>['nullable','string'],'type'=>['required','string'],'condition_key'=>['nullable','string'],'condition_value'=>['nullable','integer'],'points_reward'=>['nullable','integer'],'is_active'=>['nullable','boolean']]);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('badges','public');
        return response()->json(Badge::create($data), 201);
    }
    public function show(int $id): JsonResponse { return response()->json(Badge::withCount('users')->findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Badge::findOrFail($id)->update($request->all()); return response()->json(['message'=>'Badge mis à jour']); }
    public function destroy(int $id): JsonResponse { Badge::findOrFail($id)->delete(); return response()->json(['message'=>'Badge supprimé']); }
    public function award(Request $request, int $id, int $userId): JsonResponse
    {
        $badge = Badge::findOrFail($id);
        $user  = User::findOrFail($userId);
        if (!$user->badges()->where('badge_id',$id)->exists()) {
            $user->badges()->attach($id, ['earned_at'=>now()]);
            if ($badge->points_reward) {
                app(\App\Services\LoyaltyService::class)->awardPoints($user, $badge->points_reward, 'bonus', "Badge attribué: {$badge->name}");
            }
        }
        return response()->json(['message'=>'Badge attribué']);
    }
}
