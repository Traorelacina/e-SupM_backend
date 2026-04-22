<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FoodBox;
use App\Models\FoodBoxItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PublicFoodBoxController extends Controller
{
    /**
     * Liste toutes les boxes alimentaires actives
     * GET /api/food-boxes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Cache pour améliorer les performances (5 minutes)
            $cacheKey = 'public_food_boxes_' . md5(json_encode([
                $request->query('featured'),
                $request->query('frequency'),
                $request->query('limit'),
                $request->query('search')
            ]));

            $boxes = Cache::remember($cacheKey, 300, function () use ($request) {
                $query = FoodBox::query()
                    ->with([
                        'items' => function ($q) {
                            $q->orderBy('sort_order');
                        },
                        'items.product' => function ($q) {
                            $q->with(['primaryImage', 'category:id,name']);
                            $q->where('is_active', true)
                              ->where('is_draft', false);
                        },
                        'items.product.primaryImage',
                        'items.product.category:id,name'
                    ])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');

                // Recherche textuelle
                if ($request->has('search') && !empty($request->search)) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('tagline', 'like', "%{$search}%");
                    });
                }

                // Filtre par box en vedette
                if ($request->boolean('featured')) {
                    $query->where('is_featured', true);
                }

                // Filtre par fréquence
                if ($request->has('frequency') && in_array($request->frequency, ['weekly', 'biweekly', 'monthly'])) {
                    $query->where('frequency', $request->frequency);
                }

                // Limite le nombre de résultats
                $limit = $request->integer('limit', 100);
                $limit = min($limit, 100);
                
                $boxes = $query->limit($limit)->get();
                
                // Formatage des données
                return $boxes->map(function ($box) {
                    return $this->formatBoxResponse($box);
                })->filter()->values();
            });

            return response()->json([
                'success' => true,
                'data' => $boxes,
                'meta' => [
                    'total' => $boxes->count(),
                    'available_frequencies' => ['weekly', 'biweekly', 'monthly'],
                    'filters_applied' => [
                        'featured' => $request->boolean('featured'),
                        'frequency' => $request->get('frequency'),
                        'search' => $request->get('search'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur PublicFoodBoxController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des boxes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Détail d'une box alimentaire spécifique
     * GET /api/food-boxes/{slug}
     */
    public function show(string $identifier): JsonResponse
    {
        try {
            // Cache pour les détails (10 minutes)
            $cacheKey = 'public_food_box_' . $identifier;
            
            $boxData = Cache::remember($cacheKey, 600, function () use ($identifier) {
                // Recherche par slug ou par ID
                $box = FoodBox::with([
                    'items' => function ($q) {
                        $q->orderBy('sort_order');
                    },
                    'items.product' => function ($q) {
                        $q->with(['primaryImage', 'category:id,name']);
                        $q->where('is_active', true)
                          ->where('is_draft', false);
                    },
                    'items.product.primaryImage',
                    'items.product.category:id,name,color'
                ])
                ->where('is_active', true)
                ->where(function ($query) use ($identifier) {
                    $query->where('slug', $identifier)
                          ->orWhere('id', is_numeric($identifier) ? $identifier : 0);
                })
                ->first();

                if (!$box) {
                    return null;
                }

                // Vérifier si la box a des places disponibles
                $box->has_available_spots = $this->checkAvailability($box);
                
                // Compter les produits actifs uniquement
                $box->active_items_count = $box->items->filter(function ($item) {
                    return $item->product && $item->product->is_active && !$item->product->is_draft;
                })->count();

                return $this->formatBoxResponse($box, true);
            });

            if (!$boxData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Box non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $boxData,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur PublicFoodBoxController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la box',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupère les boxes en vedette
     * GET /api/food-boxes/featured
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $limit = $request->integer('limit', 6);
            $limit = min($limit, 12);

            $boxes = FoodBox::with([
                'items' => function ($q) {
                    $q->orderBy('sort_order')->take(5);
                },
                'items.product.primaryImage'
            ])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function ($box) {
                return $this->formatBoxResponse($box);
            });

            return response()->json([
                'success' => true,
                'data' => $boxes,
                'meta' => [
                    'total' => $boxes->count(),
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur PublicFoodBoxController@featured: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des boxes vedettes'
            ], 500);
        }
    }

    /**
     * Recherche des boxes alimentaires
     * GET /api/food-boxes/search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => ['required', 'string', 'min:2', 'max:100'],
            ]);

            $query = $request->get('q');
            
            $boxes = FoodBox::with([
                'items.product.primaryImage'
            ])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('tagline', 'like', "%{$query}%");
            })
            ->orderByRaw("CASE WHEN name LIKE ? THEN 1 ELSE 2 END", ["%{$query}%"])
            ->orderBy('sort_order')
            ->limit(20)
            ->get()
            ->map(function ($box) {
                return $this->formatBoxResponse($box);
            });

            return response()->json([
                'success' => true,
                'data' => $boxes,
                'meta' => [
                    'query' => $query,
                    'total' => $boxes->count(),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur PublicFoodBoxController@search: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * Récupère les fréquences disponibles
     * GET /api/food-boxes/frequencies
     */
    public function frequencies(): JsonResponse
    {
        $frequencies = [
            'weekly' => [
                'label' => 'Hebdomadaire',
                'description' => 'Livraison chaque semaine',
                'delivery_days' => 7,
            ],
            'biweekly' => [
                'label' => 'Bi-mensuelle',
                'description' => 'Livraison toutes les 2 semaines',
                'delivery_days' => 14,
            ],
            'monthly' => [
                'label' => 'Mensuelle',
                'description' => 'Livraison chaque mois',
                'delivery_days' => 30,
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $frequencies,
        ]);
    }

    /**
     * Vérifie la disponibilité d'une box
     * GET /api/food-boxes/{id}/availability
     */
    public function checkAvailabilityEndpoint(int $id): JsonResponse
    {
        try {
            $box = FoodBox::where('is_active', true)->find($id);
            
            if (!$box) {
                return response()->json([
                    'success' => false,
                    'message' => 'Box non trouvée'
                ], 404);
            }
            
            $availability = [
                'is_available' => $this->checkAvailability($box),
                'is_full' => $box->max_subscribers ? $box->subscribers_count >= $box->max_subscribers : false,
                'subscribers_count' => $box->subscribers_count,
                'max_subscribers' => $box->max_subscribers,
                'available_spots' => $box->max_subscribers 
                    ? max(0, $box->max_subscribers - $box->subscribers_count) 
                    : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $availability,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur PublicFoodBoxController@checkAvailabilityEndpoint: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification'
            ], 500);
        }
    }

    /**
     * Invalide le cache pour une box spécifique
     * Utile après une mise à jour dans l'admin
     */
    public function clearCache(Request $request): JsonResponse
    {
        // Cette route devrait être protégée ou appelée automatiquement
        // depuis l'admin après chaque mise à jour
        
        try {
            if ($request->has('box_id')) {
                Cache::forget('public_food_box_' . $request->box_id);
            }
            
            // Clear all food boxes cache
            $keys = Cache::get('public_food_boxes_keys', []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
            Cache::forget('public_food_boxes_keys');
            
            return response()->json([
                'success' => true,
                'message' => 'Cache invalidé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'invalidation du cache'
            ], 500);
        }
    }

    /**
     * Formatte la réponse d'une box pour l'API publique
     */
    private function formatBoxResponse(FoodBox $box, bool $detailed = false): array
    {
        try {
            $response = [
                'id' => $box->id,
                'slug' => $box->slug,
                'name' => $box->name,
                'tagline' => $box->tagline,
                'description' => $detailed ? $box->description : null,
                'price' => (float) $box->price,
                'compare_price' => $box->compare_price ? (float) $box->compare_price : null,
                'frequency' => $box->frequency,
                'is_active' => $box->is_active,
                'is_featured' => $box->is_featured,
                'badge_label' => $box->badge_label,
                'badge_color' => $box->badge_color,
                'image_url' => $this->getImageUrl($box),
                'subscribers_count' => (int) $box->subscribers_count,
                'max_subscribers' => $box->max_subscribers ? (int) $box->max_subscribers : null,
                'sort_order' => (int) $box->sort_order,
                'created_at' => $box->created_at ? $box->created_at->toISOString() : null,
                'updated_at' => $box->updated_at ? $box->updated_at->toISOString() : null,
            ];

            // Ajoute les statistiques de disponibilité
            $response['availability'] = [
                'has_available_spots' => $this->checkAvailability($box),
                'is_full' => $box->max_subscribers ? $box->subscribers_count >= $box->max_subscribers : false,
                'available_spots' => $box->max_subscribers 
                    ? max(0, $box->max_subscribers - $box->subscribers_count) 
                    : null,
            ];

            // Calcul du pourcentage de remplissage
            if ($box->max_subscribers && $box->max_subscribers > 0) {
                $response['availability']['fill_percentage'] = round(($box->subscribers_count / $box->max_subscribers) * 100);
            } else {
                $response['availability']['fill_percentage'] = null;
            }

            // Ajoute les items si demandé
            if ($detailed && $box->relationLoaded('items')) {
                $response['items'] = $box->items
                    ->filter(function ($item) {
                        return $item->product && $item->product->is_active && !$item->product->is_draft;
                    })
                    ->map(function ($item) {
                        $product = $item->product;
                        return [
                            'id' => $item->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => (int) $item->quantity,
                            'sort_order' => (int) $item->sort_order,
                            'product_price' => (float) $product->price,
                            'product_image' => $product->primaryImage 
                                ? $this->getImageUrl($product->primaryImage)
                                : null,
                            'category' => $product->category ? [
                                'id' => $product->category->id,
                                'name' => $product->category->name,
                            ] : null,
                        ];
                    })
                    ->values()
                    ->toArray();
                
                $response['total_items'] = count($response['items']);
            } elseif ($box->relationLoaded('items')) {
                $response['items_count'] = $box->items->count();
            }

            // Calcul de la réduction si compare_price existe
            if ($box->compare_price && $box->compare_price > $box->price) {
                $response['discount_percent'] = round((1 - $box->price / $box->compare_price) * 100);
                $response['discount_amount'] = round($box->compare_price - $box->price, 2);
            } else {
                $response['discount_percent'] = null;
                $response['discount_amount'] = null;
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Erreur formatBoxResponse: ' . $e->getMessage(), ['box_id' => $box->id]);
            return [
                'id' => $box->id,
                'name' => $box->name,
                'error' => 'Erreur de formatage'
            ];
        }
    }

    /**
     * Récupère l'URL complète de l'image
     */
    private function getImageUrl($model): ?string
    {
        if (!$model) return null;
        
        $imagePath = $model->image ?? $model->path ?? null;
        
        if (!$imagePath) return null;
        
        // Si c'est déjà une URL complète
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }
        
        // Supprimer le préfixe storage/ s'il existe déjà
        $imagePath = ltrim($imagePath, '/');
        if (str_starts_with($imagePath, 'storage/')) {
            $imagePath = substr($imagePath, 8);
        }
        
        // Construire l'URL
        return asset('storage/' . $imagePath);
    }

    /**
     * Vérifie si la box a des places disponibles
     */
    private function checkAvailability(FoodBox $box): bool
    {
        if (!$box->is_active) return false;
        
        if ($box->max_subscribers && $box->max_subscribers > 0) {
            return $box->subscribers_count < $box->max_subscribers;
        }
        
        return true;
    }
}