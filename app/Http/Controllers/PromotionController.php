<?php
namespace App\Http\Controllers;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $promos = Promotion::active()->with(['category','product.primaryImage'])->paginate(20);
        return response()->json($promos);
    }
    public function flash(): JsonResponse
    {
        return response()->json(Promotion::active()->flash()->with('product.primaryImage')->get());
    }
    public function soldes(): JsonResponse
    {
        return response()->json(Promotion::active()->where('type','solde')->get());
    }
    public function destockage(): JsonResponse
    {
        $products = \App\Models\Product::active()->whereNotNull('expiry_date')->whereDate('expiry_date','<=', now()->addDays(30))->with('primaryImage')->orderBy('expiry_date')->get();
        return response()->json($products);
    }
}
