<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    // ── Colonnes triables autorisées ──────────────────────────────
    private const ALLOWED_SORTS = [
        'price', 'name', 'created_at', 'updated_at', 'sales_count', 'stock',
    ];

    // =========================================================
    // PUBLIC : Liste des produits avec filtres + pagination
    // =========================================================
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::active()
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug,color', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating');

            // ── Filtre catégorie (slug) ──────────────────────
            if ($request->filled('category')) {
                $query->whereHas('category', fn($q) =>
                    $q->where('slug', $request->category)
                );
            }

            // ── Filtre product_category ──────────────────────
            if ($request->filled('product_category_id')) {
                $query->where('product_category_id', $request->product_category_id);
            }

            // ── Filtre marque ────────────────────────────────
            if ($request->filled('brand')) {
                $query->where('brand', $request->brand);
            }

            // ── Fourchette de prix ───────────────────────────
            if ($request->filled('min_price')) {
                $query->where('price', '>=', (float) $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', (float) $request->max_price);
            }

            // ── Filtres booléens ─────────────────────────────
            foreach (['is_bio', 'is_local', 'is_vegan', 'is_gluten_free', 'is_premium', 'is_new', 'is_featured'] as $flag) {
                if ($request->boolean($flag)) {
                    $query->where($flag, true);
                }
            }

            // ── En promo ─────────────────────────────────────
            if ($request->boolean('in_promo')) {
                $query->whereNotNull('compare_price')
                      ->whereRaw('compare_price > price');
            }

            // ── Recherche textuelle ──────────────────────────
            if ($request->filled('q')) {
                $q = '%' . $request->q . '%';
                $query->where(fn($sq) =>
                    $sq->where('name', 'LIKE', $q)
                       ->orWhere('description', 'LIKE', $q)
                       ->orWhere('brand', 'LIKE', $q)
                       ->orWhere('sku', 'LIKE', $q)
                );
            }

            // ── Tri ───────────────────────────────────────────
            $sort      = $request->get('sort', 'created_at');
            $direction = in_array(strtolower($request->get('direction', 'desc')), ['asc', 'desc'])
                ? $request->get('direction', 'desc')
                : 'desc';

            if ($sort === 'average_rating') {
                // Tri sur l'agrégat calculé par withAvg
                $query->orderBy('reviews_avg_rating', $direction);
            } elseif ($sort === 'sales_count') {
                $query->orderBy('sales_count', $direction);
            } elseif (in_array($sort, self::ALLOWED_SORTS)) {
                $query->orderBy($sort, $direction);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // ── Pagination ────────────────────────────────────
            $perPage  = min((int) $request->get('per_page', 20), 100);
            $products = $query->paginate($perPage);

            return response()->json([
                'data'         => $products->map(fn($p) => $this->formatProduct($p)),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ]);

        } catch (\Exception $e) {
            Log::error('ProductController@index : ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    // PUBLIC : Détail d'un produit (par slug)
    // =========================================================
    public function show(string $slug): JsonResponse
    {
        try {
            $product = Product::active()
                ->where('slug', $slug)
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with([
                    'category:id,name,slug,color',
                    'images',
                    'productCategory:id,name,color',
                ])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->firstOrFail();

            // Charger les avis séparément (relation optionnelle)
            try {
                $product->load(['reviews' => fn($q) => $q->latest()->limit(10), 'reviews.user:id,name,avatar']);
            } catch (\Exception) {
                // reviews ou user non disponibles — on continue sans
            }

            // Incrémenter les vues
            try { $product->increment('views_count'); } catch (\Exception) {}

            return response()->json($this->formatProduct($product, detailed: true));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Produit introuvable'], 404);
        } catch (\Exception $e) {
            Log::error('ProductController@show : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    // PUBLIC : Produits en vedette
    // =========================================================
    public function featured(Request $request): JsonResponse
    {
        try {
            $products = Product::active()
                ->where('is_featured', true)
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->limit((int) $request->get('limit', 12))
                ->get();

            return response()->json(
                $products->map(fn($p) => $this->formatProduct($p))->values()
            );
        } catch (\Exception $e) {
            Log::error('ProductController@featured : ' . $e->getMessage());
            return response()->json([], 200); // Retourner tableau vide plutôt que 500
        }
    }

    // =========================================================
    // PUBLIC : Nouveautés
    // =========================================================
    public function newArrivals(Request $request): JsonResponse
    {
        try {
            $products = Product::active()
                ->where('is_new', true)
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->latest()
                ->limit((int) $request->get('limit', 12))
                ->get();

            return response()->json(
                $products->map(fn($p) => $this->formatProduct($p))->values()
            );
        } catch (\Exception $e) {
            Log::error('ProductController@newArrivals : ' . $e->getMessage());
            return response()->json([], 200);
        }
    }

    // =========================================================
    // PUBLIC : Meilleures ventes
    // =========================================================
    public function bestsellers(Request $request): JsonResponse
    {
        try {
            $products = Product::active()
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->orderByDesc('sales_count')
                ->limit((int) $request->get('limit', 12))
                ->get();

            return response()->json(
                $products->map(fn($p) => $this->formatProduct($p))->values()
            );
        } catch (\Exception $e) {
            Log::error('ProductController@bestsellers : ' . $e->getMessage());
            return response()->json([], 200);
        }
    }

    // =========================================================
    // PUBLIC : Produits premium (paginés)
    // =========================================================
    public function premium(Request $request): JsonResponse
    {
        try {
            $perPage  = min((int) $request->get('per_page', 20), 100);
            $products = Product::active()
                ->where('is_premium', true)
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->paginate($perPage);

            return response()->json([
                'data'         => $products->map(fn($p) => $this->formatProduct($p)),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('ProductController@premium : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    // =========================================================
    // PUBLIC : Produits similaires
    // =========================================================
    public function related(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            $related = Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $id)
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->limit(8)
                ->get();

            return response()->json(
                $related->map(fn($p) => $this->formatProduct($p))->values()
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([], 200);
        } catch (\Exception $e) {
            Log::error('ProductController@related : ' . $e->getMessage());
            return response()->json([], 200);
        }
    }

    // =========================================================
    // PUBLIC : Produits par catégorie (route dédiée)
    // =========================================================
    public function byCategory(string $slug, Request $request): JsonResponse
    {
        try {
            $category = Category::where('slug', $slug)->firstOrFail();

            $perPage  = min((int) $request->get('per_page', 20), 100);
            $products = Product::active()
                ->where('category_id', $category->id)
                ->where(function($q) {
                    $q->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
                })
                ->with(['category:id,name,slug,color', 'primaryImage'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->paginate($perPage);

            return response()->json([
                'data'         => $products->map(fn($p) => $this->formatProduct($p)),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Catégorie introuvable'], 404);
        } catch (\Exception $e) {
            Log::error('ProductController@byCategory : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    // =========================================================
    // PUBLIC : Filtres disponibles (pour le frontend)
    // =========================================================
    public function filters(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'price_range' => [
                    'min' => (float) Product::active()
                        ->where(function($q) {
                            $q->whereNull('expiry_date')
                              ->orWhere('expiry_date', '>', now());
                        })
                        ->min('price'),
                    'max' => (float) Product::active()
                        ->where(function($q) {
                            $q->whereNull('expiry_date')
                              ->orWhere('expiry_date', '>', now());
                        })
                        ->max('price'),
                ],
                'brands' => Product::active()
                    ->whereNotNull('brand')
                    ->where(function($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                    })
                    ->select('brand')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('brand')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get(),
                'has_bio'   => Product::active()
                    ->where('is_bio', true)
                    ->where(function($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                    })
                    ->exists(),
                'has_local' => Product::active()
                    ->where('is_local', true)
                    ->where(function($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                    })
                    ->exists(),
                'has_promo' => Product::active()
                    ->whereNotNull('compare_price')
                    ->whereRaw('compare_price > price')
                    ->where(function($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                    })
                    ->exists(),
            ]);
        } catch (\Exception $e) {
            Log::error('ProductController@filters : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    // =========================================================
    // Formatage uniforme d'un produit pour le frontend
    // =========================================================
    private function formatProduct(Product $product, bool $detailed = false): array
    {
        // Image principale
        $primaryImageUrl = null;
        if ($product->relationLoaded('primaryImage') && $product->primaryImage) {
            $primaryImageUrl = asset('storage/' . $product->primaryImage->path);
        } elseif ($product->relationLoaded('images') && $product->images->isNotEmpty()) {
            $primary = $product->images->firstWhere('is_primary', true)
                    ?? $product->images->first();
            $primaryImageUrl = $primary ? asset('storage/' . $primary->path) : null;
        }

        // Calculs dérivés
        $inStock  = $product->stock > 0;
        $lowStock = $inStock && $product->stock <= max(($product->low_stock_threshold ?? 5), 1);

        $discountPercentage = null;
        if ($product->compare_price && $product->compare_price > $product->price) {
            $discountPercentage = (int) round(
                (1 - $product->price / $product->compare_price) * 100
            );
        }

        // Moyenne des avis (withAvg renomme le champ)
        $avgRating   = $product->reviews_avg_rating
            ? round((float) $product->reviews_avg_rating, 1)
            : null;
        $reviewCount = $product->reviews_count ?? 0;

        // Gestion de la date d'expiration
        $expiryDate = $product->expiry_date ? $product->expiry_date->toISOString() : null;
        $isExpired = $product->expiry_date && $product->expiry_date->isPast();
        $isExpiringSoon = $product->expiry_date && 
            $product->expiry_date->isFuture() && 
            $product->expiry_date->diffInDays(now()) <= 30;
        $daysUntilExpiry = $product->expiry_date && $product->expiry_date->isFuture() 
            ? $product->expiry_date->diffInDays(now()) 
            : null;

        $base = [
            'id'                  => $product->id,
            'name'                => $product->name,
            'slug'                => $product->slug,
            'description'         => $product->description,
            'price'               => (float) $product->price,
            'compare_price'       => $product->compare_price ? (float) $product->compare_price : null,
            'cost_price'          => $product->cost_price   ? (float) $product->cost_price   : null,
            'discount_percentage' => $discountPercentage,
            'stock'               => $product->stock,
            'low_stock_threshold' => $product->low_stock_threshold ?? 5,
            'in_stock'            => $inStock,
            'is_low_stock'        => $lowStock,
            'sku'                 => $product->sku,
            'barcode'             => $product->barcode,
            'brand'               => $product->brand ?? null,
            'unit'                => $product->unit  ?? null,
            'weight'              => $product->weight ?? null,
            'origin'              => $product->origin ?? null,
            'is_active'           => (bool) $product->is_active,
            'is_draft'            => (bool) ($product->is_draft ?? false),
            'is_bio'              => (bool) $product->is_bio,
            'is_local'            => (bool) $product->is_local,
            'is_eco'              => (bool) ($product->is_eco        ?? false),
            'is_vegan'            => (bool) ($product->is_vegan      ?? false),
            'is_gluten_free'      => (bool) ($product->is_gluten_free ?? false),
            'is_premium'          => (bool) $product->is_premium,
            'is_featured'         => (bool) $product->is_featured,
            'is_new'              => (bool) $product->is_new,
            'admin_label'         => $product->admin_label         ?? null,
            'admin_label_discount'=> $product->admin_label_discount ?? null,
            'average_rating'      => $avgRating,
            'reviews_count'       => $reviewCount,
            'sales_count'         => $product->sales_count  ?? 0,
            'views_count'         => $product->views_count  ?? 0,
            'primary_image_url'   => $primaryImageUrl,
            'category_id'         => $product->category_id,
            'category'            => $product->relationLoaded('category') && $product->category
                ? [
                    'id'    => $product->category->id,
                    'name'  => $product->category->name,
                    'slug'  => $product->category->slug,
                    'color' => $product->category->color,
                ]
                : null,
            'product_category_id' => $product->product_category_id ?? null,
            'product_category'    => $product->relationLoaded('productCategory') && $product->productCategory
                ? [
                    'id'    => $product->productCategory->id,
                    'name'  => $product->productCategory->name,
                    'color' => $product->productCategory->color,
                ]
                : null,
            // Dates
            'expiry_date'         => $expiryDate,
            'is_expired'          => $isExpired,
            'is_expiring_soon'    => $isExpiringSoon,
            'days_until_expiry'   => $daysUntilExpiry,
            'created_at'          => $product->created_at?->toISOString(),
            'updated_at'          => $product->updated_at?->toISOString(),
        ];

        // Champs supplémentaires pour la page détail
        if ($detailed) {
            $base['images'] = $product->relationLoaded('images')
                ? $product->images->map(fn($img) => [
                    'id'         => $img->id,
                    'path'       => $img->path,
                    'url'        => asset('storage/' . $img->path),
                    'is_primary' => (bool) $img->is_primary,
                    'sort_order' => $img->sort_order ?? 0,
                ])->values()
                : [];

            $base['reviews'] = $product->relationLoaded('reviews')
                ? $product->reviews->map(fn($r) => [
                    'id'         => $r->id,
                    'rating'     => $r->rating,
                    'comment'    => $r->comment  ?? null,
                    'created_at' => $r->created_at?->toISOString(),
                    'user'       => $r->relationLoaded('user') && $r->user
                        ? ['id' => $r->user->id, 'name' => $r->user->name, 'avatar' => $r->user->avatar ?? null]
                        : null,
                ])->values()
                : [];
        }

        return $base;
    }
}