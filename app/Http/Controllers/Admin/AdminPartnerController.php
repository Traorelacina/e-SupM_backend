<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminPartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Partner::with('user:id,name,email');
        if ($request->status) $q->where('status', $request->status);
        return response()->json($q->latest()->paginate(20));
    }
    public function show(int $id): JsonResponse { return response()->json(Partner::with(['user','products'])->findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Partner::findOrFail($id)->update($request->except(['status'])); return response()->json(['message'=>'Partenaire mis à jour']); }
    public function approve(int $id): JsonResponse
    {
        $p = Partner::findOrFail($id);
        $p->update(['status'=>'approved','approved_at'=>now()]);
        // Notify partner
        if ($p->user) $p->user->notify(new \App\Notifications\PartnerApprovedNotification($p));
        return response()->json(['message'=>'Partenaire approuvé']);
    }
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason'=>['required','string']]);
        $p = Partner::findOrFail($id);
        $p->update(['status'=>'rejected','rejection_reason'=>$request->reason]);
        if ($p->user) $p->user->notify(new \App\Notifications\PartnerRejectedNotification($p, $request->reason));
        return response()->json(['message'=>'Candidature rejetée']);
    }
    public function destroy(int $id): JsonResponse { Partner::findOrFail($id)->delete(); return response()->json(['message'=>'Partenaire supprimé']); }
}
