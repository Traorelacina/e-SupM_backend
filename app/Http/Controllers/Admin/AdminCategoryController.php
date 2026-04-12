<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    // ==================== CATEGORIES (Rayons) ====================
    
    public function index(): JsonResponse
    {
        $categories = Category::with(['children', 'productCategories'])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($cat) => $this->appendImageUrl($cat));

        return response()->json($categories);
    }

    public function tree(): JsonResponse
    {
        return $this->index();
    }

    public function show($id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }
        $cat = Category::with(['children', 'parent', 'productCategories'])->findOrFail((int) $id);
        return response()->json($this->appendImageUrl($cat));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Ne valider que les champs de Category, pas product_categories
            $data = $request->validate([
                'name'         => ['required', 'string', 'max:255'],
                'parent_id'    => ['nullable', 'exists:categories,id'],
                'description'  => ['nullable', 'string'],
                'color'        => ['nullable', 'string', 'max:20'],
                'is_active'    => ['nullable'],
                'is_premium'   => ['nullable'],
                'show_in_menu' => ['nullable'],
                'sort_order'   => ['nullable', 'integer'],
                'image'        => ['nullable', 'image', 'max:5120'],
            ]);

            // Récupérer les catégories de produits séparément
            $productCategoriesData = $request->input('product_categories', []);

            $data['is_active']    = $this->parseBool($request->is_active, true);
            $data['is_premium']   = $this->parseBool($request->is_premium, false);
            $data['show_in_menu'] = $this->parseBool($request->show_in_menu, true);
            $data['parent_id']    = $request->parent_id ?: null;

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('categories', 'public');
                $data['image'] = $path;
            }

            $data['slug'] = $this->uniqueSlug($data['name']);

            $cat = Category::create($data);

            // Créer les catégories de produits associées
            if (is_array($productCategoriesData)) {
                foreach ($productCategoriesData as $index => $pcData) {
                    if (!empty($pcData['name'])) {
                        ProductCategory::create([
                            'category_id' => $cat->id,
                            'name' => $pcData['name'],
                            'color' => $pcData['color'] ?? '#FBBF24',
                            'description' => $pcData['description'] ?? null,
                            'sort_order' => $pcData['sort_order'] ?? $index,
                            'slug' => $this->uniqueProductCategorySlug($pcData['name']),
                            'is_active' => true,
                        ]);
                    }
                }
            }

            return response()->json($this->appendImageUrl($cat->load('productCategories')), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création rayon: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $cat = Category::findOrFail((int) $id);

            // Ne valider que les champs de Category
            $data = $request->validate([
                'name'         => ['sometimes', 'required', 'string', 'max:255'],
                'parent_id'    => ['nullable', 'exists:categories,id'],
                'description'  => ['nullable', 'string'],
                'color'        => ['nullable', 'string', 'max:20'],
                'is_active'    => ['nullable'],
                'is_premium'   => ['nullable'],
                'show_in_menu' => ['nullable'],
                'sort_order'   => ['nullable', 'integer'],
                'image'        => ['nullable', 'image', 'max:5120'],
            ]);

            // Récupérer les catégories de produits et les IDs à supprimer
            $productCategoriesData = $request->input('product_categories', []);
            $categoriesToDelete = $request->input('product_categories_to_delete', []);

            if ($request->has('is_active'))    $data['is_active']    = $this->parseBool($request->is_active);
            if ($request->has('is_premium'))   $data['is_premium']   = $this->parseBool($request->is_premium);
            if ($request->has('show_in_menu')) $data['show_in_menu'] = $this->parseBool($request->show_in_menu);

            if ($request->has('parent_id')) {
                $data['parent_id'] = $request->parent_id ?: null;
            }

            if ($request->hasFile('image')) {
                if ($cat->image) {
                    Storage::disk('public')->delete($cat->image);
                }
                $path = $request->file('image')->store('categories', 'public');
                $data['image'] = $path;
            }

            unset($data['slug'], $data['_method']);

            $cat->update($data);

            // Supprimer les catégories de produits marquées
            if (is_array($categoriesToDelete) && !empty($categoriesToDelete)) {
                ProductCategory::whereIn('id', $categoriesToDelete)
                    ->where('category_id', $cat->id)
                    ->delete();
            }

            // Mettre à jour ou créer les catégories de produits
            if (is_array($productCategoriesData)) {
                foreach ($productCategoriesData as $pcData) {
                    if (empty($pcData['name'])) continue;
                    
                    if (isset($pcData['id']) && !empty($pcData['id'])) {
                        // Vérifier que la catégorie appartient bien à ce rayon
                        $existing = ProductCategory::where('id', $pcData['id'])
                            ->where('category_id', $cat->id)
                            ->first();
                        if ($existing) {
                            $existing->update([
                                'name' => $pcData['name'],
                                'color' => $pcData['color'] ?? '#FBBF24',
                                'description' => $pcData['description'] ?? null,
                                'sort_order' => $pcData['sort_order'] ?? 0,
                            ]);
                        }
                    } else {
                        // Création
                        ProductCategory::create([
                            'category_id' => $cat->id,
                            'name' => $pcData['name'],
                            'color' => $pcData['color'] ?? '#FBBF24',
                            'description' => $pcData['description'] ?? null,
                            'sort_order' => $pcData['sort_order'] ?? 0,
                            'slug' => $this->uniqueProductCategorySlug($pcData['name']),
                            'is_active' => true,
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rayon mis à jour',
                'data'    => $this->appendImageUrl($cat->fresh()->load('productCategories')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour rayon: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $cat = Category::findOrFail((int) $id);

            // Supprimer les catégories de produits associées
            foreach ($cat->productCategories as $pc) {
                if ($pc->image) {
                    Storage::disk('public')->delete($pc->image);
                }
                $pc->delete();
            }

            if ($cat->image) {
                Storage::disk('public')->delete($cat->image);
            }

            $cat->delete();

            return response()->json(['success' => true, 'message' => 'Rayon supprimé']);
        } catch (\Exception $e) {
            Log::error('Erreur suppression rayon: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request, $id): JsonResponse
    {
        $request->validate(['sort_order' => ['required', 'integer', 'min:0']]);
        Category::findOrFail((int) $id)->update(['sort_order' => $request->sort_order]);
        return response()->json(['success' => true, 'message' => 'Ordre mis à jour']);
    }

    public function toggle($id): JsonResponse
    {
        $cat = Category::findOrFail((int) $id);
        $newStatus = !$cat->is_active;
        $cat->update(['is_active' => $newStatus]);

        $updatedCat = $this->appendImageUrl($cat->fresh());

        return response()->json([
            'success'   => true,
            'is_active' => $newStatus,
            'message'   => $newStatus ? 'Rayon activé' : 'Rayon désactivé',
            'data'      => $updatedCat,
        ]);
    }

    // ==================== CATÉGORIES DE PRODUITS ====================
    
    public function productCategories(Request $request): JsonResponse
    {
        $q = ProductCategory::with('category:id,name,color');

        if ($request->category_id) {
            $q->where('category_id', $request->category_id);
        }

        if ($request->active === 'true') {
            $q->active();
        }

        if ($request->q) {
            $q->where('name', 'like', "%{$request->q}%");
        }

        $categories = $q->orderBy('category_id')->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    public function grouped(): JsonResponse
    {
        $rayons = Category::active()
            ->with(['productCategories' => function($q) {
                $q->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $grouped = $rayons->map(fn($rayon) => [
            'id'                 => $rayon->id,
            'name'               => $rayon->name,
            'color'              => $rayon->color,
            'product_categories' => $rayon->productCategories->map(fn($pc) => $this->appendProductCategoryImageUrl($pc)),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    public function storeProductCategory(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'category_id' => ['required', 'exists:categories,id'],
                'name'        => ['required', 'string', 'max:255'],
                'name_en'     => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'color'       => ['nullable', 'string', 'max:20'],
                'sort_order'  => ['nullable', 'integer', 'min:0'],
                'is_active'   => ['nullable', 'boolean'],
                'image'       => ['nullable', 'image', 'max:2048'],
            ]);

            if (isset($data['is_active'])) {
                $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }

            $data['slug'] = $this->uniqueProductCategorySlug($data['name']);

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('product-categories', 'public');
            }

            $productCategory = ProductCategory::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie créée avec succès',
                'data'    => $this->appendProductCategoryImageUrl($productCategory->load('category:id,name')),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création ProductCategory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateProductCategory(Request $request, int $id): JsonResponse
    {
        try {
            $productCategory = ProductCategory::findOrFail($id);

            $data = $request->validate([
                'category_id' => ['sometimes', 'exists:categories,id'],
                'name'        => ['sometimes', 'string', 'max:255'],
                'name_en'     => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'color'       => ['nullable', 'string', 'max:20'],
                'sort_order'  => ['nullable', 'integer', 'min:0'],
                'is_active'   => ['nullable'],
                'image'       => ['nullable', 'image', 'max:2048'],
            ]);

            if ($request->has('is_active')) {
                $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->hasFile('image')) {
                if ($productCategory->image) {
                    Storage::disk('public')->delete($productCategory->image);
                }
                $data['image'] = $request->file('image')->store('product-categories', 'public');
            }

            $productCategory->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie mise à jour',
                'data'    => $this->appendProductCategoryImageUrl($productCategory->fresh()->load('category:id,name')),
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur update ProductCategory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroyProductCategory(int $id): JsonResponse
    {
        try {
            $productCategory = ProductCategory::findOrFail($id);

            if ($productCategory->image) {
                Storage::disk('public')->delete($productCategory->image);
            }

            $productCategory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie supprimée',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression ProductCategory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleProductCategory(int $id): JsonResponse
    {
        try {
            $productCategory = ProductCategory::findOrFail($id);
            $productCategory->update(['is_active' => !$productCategory->is_active]);

            return response()->json([
                'success'   => true,
                'is_active' => $productCategory->is_active,
                'message'   => $productCategory->is_active ? 'Catégorie activée' : 'Catégorie désactivée',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur toggle ProductCategory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
            ], 500);
        }
    }

    // ==================== HELPERS ====================

    private function appendImageUrl(Category $cat): Category
    {
        $cat->image_url = $cat->image ? asset('storage/' . $cat->image) : null;

        if ($cat->relationLoaded('children') && $cat->children) {
            $cat->setRelation('children', $cat->children->map(
                fn($child) => $this->appendImageUrl($child)
            ));
        }

        if ($cat->relationLoaded('productCategories') && $cat->productCategories) {
            $cat->setRelation('productCategories', $cat->productCategories->map(
                fn($pc) => $this->appendProductCategoryImageUrl($pc)
            ));
        }

        return $cat;
    }

    private function appendProductCategoryImageUrl(ProductCategory $pc): ProductCategory
    {
        $pc->image_url = $pc->image ? asset('storage/' . $pc->image) : null;
        return $pc;
    }

    private function parseBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) return $default;
        if (is_bool($value)) return $value;
        return in_array($value, ['1', 'true', true, 1], true);
    }

    private function uniqueSlug(string $name): string
    {
        $base    = Str::slug($name);
        $slug    = $base;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function uniqueProductCategorySlug(string $name): string
    {
        $base    = Str::slug($name);
        $slug    = $base;
        $counter = 1;
        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }
}