<?php
namespace App\Http\Controllers;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::active()->root()->inMenu()->with('children')->orderBy('sort_order')->get();
        return response()->json($categories);
    }

    public function tree(): JsonResponse
    {
        $categories = Category::active()->root()->with(['children.children'])->orderBy('sort_order')->get();
        return response()->json($categories);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::active()->where('slug', $slug)->with(['children', 'parent'])->firstOrFail();
        return response()->json($category);
    }

    public function products(Request $request, string $slug): JsonResponse
    {
        $category = Category::active()->where('slug', $slug)->firstOrFail();

        // Include subcategory products
        $categoryIds = [$category->id, ...$category->children->pluck('id')];

        $products = \App\Models\Product::active()
            ->whereIn('category_id', $categoryIds)
            ->with('primaryImage')
            ->paginate($request->get('per_page', 20));

        return response()->json($products);
    }
}
