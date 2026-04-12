<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Liste des produits avec filtres et pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::active()
            ->with(['category', 'primaryImage'])
            ->withCount(['reviews']);

        // Filtre par catégorie
        if ($request->filled('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filtre par marque
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        // Filtre par prix
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Filtres spéciaux
        if ($request->boolean('is_bio')) {
            $query->where('is_bio', true);
        }
        if ($request->boolean('is_local')) {
            $query->where('is_local', true);
        }
        if ($request->boolean('is_vegan')) {
            $query->where('is_vegan', true);
        }
        if ($request->boolean('is_gluten_free')) {
            $query->where('is_gluten_free', true);
        }
        if ($request->boolean('is_premium')) {
            $query->where('is_premium', true);
        }
        if ($request->boolean('in_promo')) {
            $query->whereNotNull('compare_price')
                  ->whereRaw('compare_price > price');
        }

        // Recherche textuelle
        if ($request->filled('q')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->q . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->q . '%')
                  ->orWhere('brand', 'LIKE', '%' . $request->q . '%');
            });
        }

        // Tri
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $allowedSorts = ['price', 'name', 'created_at', 'sales_count', 'average_rating', 'updated_at'];
        
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json(new ProductCollection($products));
    }

    /**
     * Détail d'un produit
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->with([
                'category',
                'images',
                'reviews' => function($q) {
                    $q->latest()->limit(10);
                },
                'reviews.user',
                'partner',
                'sizeOptions'
            ])
            ->withCount(['reviews'])
            ->firstOrFail();

        // Incrémenter le compteur de vues
        $product->increment('views_count');

        return response()->json(new ProductResource($product));
    }

    /**
     * Produits en vedette
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 12);
        
        $products = Product::active()
            ->featured()
            ->with('primaryImage')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => ['count' => $products->count()]
        ]);
    }

    /**
     * Nouveautés
     */
    public function newArrivals(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 12);
        
        $products = Product::active()
            ->new()
            ->with('primaryImage')
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => ['count' => $products->count()]
        ]);
    }

    /**
     * Meilleures ventes
     */
    public function bestsellers(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 12);
        
        $products = Product::active()
            ->orderByDesc('sales_count')
            ->with('primaryImage')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => ['count' => $products->count()]
        ]);
    }

    /**
     * Produits premium
     */
    public function premium(Request $request): JsonResponse
    {
        $query = Product::active()
            ->premium()
            ->with('primaryImage');

        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json(new ProductCollection($products));
    }

    /**
     * Produits similaires
     */
    public function related(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        
        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->with('primaryImage')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($related),
            'meta' => ['count' => $related->count()]
        ]);
    }

    /**
     * Produits par catégorie
     */
    public function byCategory(string $slug, Request $request): JsonResponse
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        
        $query = Product::active()
            ->where('category_id', $category->id)
            ->with(['category', 'primaryImage']);

        $perPage = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json(new ProductCollection($products));
    }

    /**
     * Filtres disponibles (pour le frontend)
     */
    public function filters(Request $request): JsonResponse
    {
        $query = Product::active();

        // Prix min et max
        $minPrice = $query->min('price');
        $maxPrice = $query->max('price');

        // Marques populaires
        $brands = Product::active()
            ->whereNotNull('brand')
            ->select('brand')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('brand')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'price_range' => [
                'min' => (float) $minPrice,
                'max' => (float) $maxPrice,
            ],
            'brands' => $brands,
            'has_bio' => Product::active()->where('is_bio', true)->exists(),
            'has_local' => Product::active()->where('is_local', true)->exists(),
            'has_promo' => Product::active()->whereNotNull('compare_price')->exists(),
        ]);
    }
}