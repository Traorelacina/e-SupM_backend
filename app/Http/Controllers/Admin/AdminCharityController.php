<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\CharityDonation;
use App\Models\CharityVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminCharityController extends Controller
{
    public function donations(Request $request): JsonResponse
    {
        $q = CharityDonation::with(['user:id,name,email','product:id,name']);
        if ($request->status) $q->where('status', $request->status);
        return response()->json($q->latest()->paginate(20));
    }
    public function vouchers(): JsonResponse { return response()->json(CharityVoucher::with(['donation.user'])->latest()->paginate(20)); }
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status'=>['required','in:pending,confirmed,distributed']]);
        CharityDonation::findOrFail($id)->update(['status'=>$request->status]);
        return response()->json(['message'=>'Statut mis à jour']);
    }
    public function impact(): JsonResponse
    {
        return response()->json([
            'total_donated'   => CharityDonation::where('status','confirmed')->sum('amount'),
            'donations_count' => CharityDonation::where('status','confirmed')->count(),
            'vouchers_issued' => CharityVoucher::count(),
            'vouchers_used'   => CharityVoucher::where('is_used',true)->count(),
            'products_gifted' => CharityDonation::where('type','product')->where('status','confirmed')->sum('quantity'),
        ]);
    }
}
