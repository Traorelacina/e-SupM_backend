<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::active()
            ->root()
            ->inMenu()
            ->with('children')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($cat) => $this->appendImageUrl($cat));

        return response()->json($categories);
    }

    public function tree(): JsonResponse
    {
        $categories = Category::active()
            ->root()
            ->with(['children.children'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn($cat) => $this->appendImageUrl($cat));

        return response()->json($categories);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::active()
            ->where('slug', $slug)
            ->with(['children', 'parent'])
            ->firstOrFail();

        return response()->json($this->appendImageUrl($category));
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

    // ── Helper ────────────────────────────────────────────────

    private function appendImageUrl(Category $cat): Category
    {
        $cat->image_url = $cat->image
            ? asset('storage/' . $cat->image)
            : null;

        if ($cat->relationLoaded('children') && $cat->children) {
            $cat->setRelation(
                'children',
                $cat->children->map(fn($child) => $this->appendImageUrl($child))
            );
        }

        return $cat;
    }
}