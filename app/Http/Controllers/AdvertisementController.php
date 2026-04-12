<?php
namespace App\Http\Controllers;
use App\Models\Advertisement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ads = Advertisement::active()
            ->when($request->position, fn($q,$p) => $q->where('position',$p))
            ->when($request->page, fn($q,$pg) => $q->whereIn('page',[$pg,'all']))
            ->orderBy('sort_order')->get();
        return response()->json($ads);
    }
    public function registerClick(int $id): JsonResponse
    {
        Advertisement::findOrFail($id)->increment('clicks_count');
        return response()->json(['message' => 'ok']);
    }
}
