<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function index(Request $request): JsonResponse
    {
        $q = User::query();
        if ($request->role)   $q->where('role', $request->role);
        if ($request->status) $q->where('status', $request->status);
        if ($request->search) $q->where(fn($q) => $q->where('name','like',"%{$request->search}%")->orWhere('email','like',"%{$request->search}%")->orWhere('phone','like',"%{$request->search}%"));
        return response()->json($q->withCount('orders')->latest()->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(User::withCount(['orders','reviews'])->with(['badges','defaultAddress'])->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update($request->only(['name','phone','language']));
        return response()->json($user);
    }

    public function destroy(int $id): JsonResponse
    {
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'Compte supprimé']);
    }

    public function ban(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['required','string','max:255']]);
        User::findOrFail($id)->update(['status'=>'banned','ban_reason'=>$request->reason,'banned_at'=>now()]);
        return response()->json(['message' => 'Utilisateur banni']);
    }

    public function unban(int $id): JsonResponse
    {
        User::findOrFail($id)->update(['status'=>'active','ban_reason'=>null,'banned_at'=>null]);
        return response()->json(['message' => 'Utilisateur débanni']);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role'=>['required','in:admin,client,preparateur,livreur,partner']]);
        User::findOrFail($id)->update(['role'=>$request->role]);
        return response()->json(['message' => 'Rôle mis à jour']);
    }

    public function userOrders(int $id): JsonResponse
    {
        return response()->json(User::findOrFail($id)->orders()->with('items')->latest()->paginate(10));
    }

    public function addLoyaltyPoints(Request $request, int $id): JsonResponse
    {
        $request->validate(['points'=>['required','integer'],'reason'=>['nullable','string']]);
        $user = User::findOrFail($id);
        $this->loyaltyService->awardPoints($user, $request->points, 'bonus', $request->reason ?? 'Ajout manuel par admin');
        return response()->json(['message'=>"$request->points points ajoutés", 'total'=>$user->fresh()->loyalty_points]);
    }
}
