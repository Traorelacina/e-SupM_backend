<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $q = $request->get('q');

        $products = Product::active()
            ->with('primaryImage')
            ->where(function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', '=', $q)
                    ->orWhereHas('category', fn($cq) => $cq->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('sales_count')
            ->paginate($request->get('per_page', 20));

        return response()->json($products);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);

        $products = Product::active()
            ->select('id', 'name', 'slug', 'price')
            ->where('name', 'like', "%{$q}%")
            ->with('primaryImage')
            ->limit(8)->get();

        return response()->json($products);
    }

    public function visualSearch(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:5120']]);
        // In a real app, this would use an ML/Vision API
        // For now we return a generic response
        return response()->json(['message' => 'Fonctionnalité de reconnaissance visuelle en cours d\'intégration', 'products' => []]);
    }
}
