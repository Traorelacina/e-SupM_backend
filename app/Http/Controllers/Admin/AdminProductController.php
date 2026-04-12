<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Product::with(['category:id,name', 'primaryImage']);
        
        // Ne pas inclure les soft deleted par défaut
        if ($request->trashed === 'true') {
            $q->onlyTrashed();
        }
        
        if ($request->category_id) $q->where('category_id', $request->category_id);
        if ($request->status === 'active')   $q->where('is_active', true)->where('is_draft', false);
        if ($request->status === 'inactive') $q->where('is_active', false)->where('is_draft', false);
        if ($request->status === 'draft')    $q->where('is_draft', true);
        if ($request->low_stock)    $q->lowStock();
        if ($request->out_of_stock) $q->outOfStock();
        if ($request->q) $q->where('name', 'like', "%{$request->q}%");
        
        $products = $q->latest()->paginate(25);
        
        // Ajouter les compteurs pour les alertes
        $response = [
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'low_stock_count' => Product::lowStock()->count(),
            'out_of_stock_count' => Product::outOfStock()->count(),
        ];
        
        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Tentative de création produit', $request->all());
            
            $data = $request->validate([
                'category_id'       => ['required', 'exists:categories,id'],
                'name'              => ['required', 'string', 'max:255'],
                'description'       => ['nullable', 'string'],
                'price'             => ['required', 'numeric', 'min:0'],
                'compare_price'     => ['nullable', 'numeric'],
                'cost_price'        => ['nullable', 'numeric'],
                'weight'            => ['nullable', 'numeric'],
                'unit'              => ['nullable', 'string'],
                'brand'             => ['nullable', 'string'],
                'origin'            => ['nullable', 'string'],
                'stock'             => ['required', 'integer', 'min:0'],
                'low_stock_threshold'=> ['nullable', 'integer'],
                'sku'               => ['nullable', 'string', 'unique:products,sku'],
                'barcode'           => ['nullable', 'string'],
                'is_bio'            => ['nullable', 'boolean'],
                'is_local'          => ['nullable', 'boolean'],
                'is_eco'            => ['nullable', 'boolean'],
                'is_vegan'          => ['nullable', 'boolean'],
                'is_gluten_free'    => ['nullable', 'boolean'],
                'is_premium'        => ['nullable', 'boolean'],
                'is_featured'       => ['nullable', 'boolean'],
                'is_new'            => ['nullable', 'boolean'],
                'is_active'         => ['nullable', 'boolean'],
                'is_draft'          => ['nullable', 'boolean'],
                'admin_label'       => ['nullable', 'string'],
                'admin_label_discount' => ['nullable', 'integer'],
                'expiry_date'       => ['nullable', 'date'],
                'partner_id'        => ['nullable', 'exists:partners,id'],
            ]);

            // Génération du slug unique
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            $data['slug'] = $slug;
            
            // Par défaut, si c'est un brouillon, is_active = false
            if (isset($data['is_draft']) && $data['is_draft']) {
                $data['is_active'] = false;
            }

            $product = DB::transaction(function () use ($request, $data) {
                $product = Product::create($data);
                
                // Handle images
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $idx => $file) {
                        $path = $file->store("products/{$product->id}", 'public');
                        ProductImage::create([
                            'product_id' => $product->id, 
                            'path' => $path, 
                            'is_primary' => $idx === 0, 
                            'sort_order' => $idx
                        ]);
                    }
                }
                
                return $product->load(['images', 'category']);
            });
            
            Log::info('Produit créé avec succès', ['id' => $product->id, 'name' => $product->name]);
            
            return response()->json([
                'success' => true,
                'message' => isset($data['is_draft']) && $data['is_draft'] ? 'Brouillon sauvegardé' : 'Produit créé avec succès',
                'data' => $product
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création produit: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::withTrashed()->with(['category', 'images', 'partner', 'reviews'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            
            // Liste des champs autorisés pour la mass assignment
            $allowedFields = [
                'category_id', 'partner_id', 'name', 'description',
                'price', 'compare_price', 'cost_price', 'weight', 'unit', 
                'brand', 'origin', 'stock', 'low_stock_threshold',
                'sku', 'barcode', 'is_bio', 'is_local', 'is_eco', 
                'is_vegan', 'is_gluten_free', 'is_premium', 'is_featured', 
                'is_new', 'is_active', 'is_draft', 'admin_label', 
                'admin_label_discount', 'expiry_date'
            ];
            
            // Ne prendre que les champs autorisés
            $data = $request->only($allowedFields);
            
            // Convertir les valeurs booléennes
            $booleanFields = [
                'is_bio', 'is_local', 'is_eco', 'is_vegan', 
                'is_gluten_free', 'is_premium', 'is_featured', 
                'is_new', 'is_active', 'is_draft'
            ];
            
            foreach ($booleanFields as $field) {
                if ($request->has($field)) {
                    $data[$field] = $this->parseBool($request->input($field));
                }
            }
            
            // Convertir les champs numériques
            $numericFields = ['price', 'compare_price', 'cost_price', 'weight', 'stock', 'low_stock_threshold', 'admin_label_discount'];
            foreach ($numericFields as $field) {
                if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                    $data[$field] = floatval($request->input($field));
                }
            }
            
            // Si le produit passe de brouillon à publié
            if (isset($data['is_draft']) && !$data['is_draft'] && $product->is_draft) {
                $data['is_active'] = true;
            }
            
            // Si le produit devient brouillon, le désactiver
            if (isset($data['is_draft']) && $data['is_draft']) {
                $data['is_active'] = false;
            }
            
            // Ne jamais modifier ces champs via update massif
            unset($data['slug'], $data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
            
            $product->update($data);
            
            Log::info('Produit mis à jour', ['id' => $product->id, 'data' => $data]);
            
            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour avec succès',
                'data' => $product->fresh()->load(['category', 'images'])
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour produit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            
            // Supprimer les images associées
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
            
            $product->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Produit supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression produit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);
            $product->restore();
            
            return response()->json([
                'success' => true,
                'message' => 'Produit restauré avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration'
            ], 500);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $newStatus = !$product->is_active;
            
            // Si on active le produit, on enlève le flag draft
            if ($newStatus) {
                $product->update([
                    'is_active' => true,
                    'is_draft' => false
                ]);
            } else {
                $product->update(['is_active' => false]);
            }
            
            return response()->json([
                'success' => true,
                'is_active' => $newStatus,
                'is_draft' => false,
                'message' => $newStatus ? 'Produit activé' : 'Produit désactivé'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }

    public function duplicate(int $id): JsonResponse
    {
        try {
            $original = Product::with('images')->findOrFail($id);
            $copy = $original->replicate(['sku', 'barcode', 'sales_count', 'views_count', 'reviews_count']);
            $copy->name = $original->name . ' (Copie)';
            $copy->slug = Str::slug($copy->name) . '-' . Str::random(6);
            $copy->stock = 0;
            $copy->is_active = false;
            $copy->is_draft = true;
            $copy->save();
            
            foreach ($original->images as $img) {
                $copy->images()->create([
                    'path' => $img->path, 
                    'is_primary' => $img->is_primary, 
                    'sort_order' => $img->sort_order
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Produit dupliqué avec succès',
                'data' => $copy->load('images')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication'
            ], 500);
        }
    }

    public function uploadImages(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'images' => ['required', 'array'],
                'images.*' => ['image', 'max:5120']
            ]);
            
            $product = Product::findOrFail($id);
            $uploaded = [];
            $hasPrimary = $product->images()->where('is_primary', true)->exists();
            
            foreach ($request->file('images') as $idx => $file) {
                $path = $file->store("products/{$id}", 'public');
                $img = ProductImage::create([
                    'product_id' => $id, 
                    'path' => $path, 
                    'is_primary' => !$hasPrimary && $idx === 0, 
                    'sort_order' => $product->images()->count() + $idx
                ]);
                $uploaded[] = $img;
                $hasPrimary = true;
            }
            
            return response()->json([
                'success' => true,
                'message' => count($uploaded) . ' image(s) ajoutée(s)',
                'data' => $uploaded
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload des images'
            ], 500);
        }
    }

    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        try {
            $image = ProductImage::where('product_id', $id)->findOrFail($imageId);
            Storage::disk('public')->delete($image->path);
            $image->delete();
            
            // Si l'image supprimée était primaire, définir une nouvelle image primaire
            if ($image->is_primary) {
                $newPrimary = ProductImage::where('product_id', $id)->first();
                if ($newPrimary) {
                    $newPrimary->update(['is_primary' => true]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Image supprimée'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'image'
            ], 500);
        }
    }

    public function updateStock(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'stock' => ['required', 'integer', 'min:0'],
                'reason' => ['nullable', 'string']
            ]);
            
            $product = Product::findOrFail($id);
            $old = $product->stock;
            $product->update(['stock' => $request->stock]);
            
            if ($request->stock > 0 && $product->admin_label === 'stock_epuise') {
                $product->update(['admin_label' => 'none']);
            }
            
            return response()->json([
                'success' => true,
                'message' => "Stock mis à jour: {$old} → {$request->stock}",
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du stock'
            ], 500);
        }
    }

    public function lowStock(): JsonResponse
    {
        $products = Product::lowStock()->with(['category:id,name', 'primaryImage'])->get();
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function outOfStock(): JsonResponse
    {
        $products = Product::outOfStock()->with(['category:id,name', 'primaryImage'])->get();
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function setLabel(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'label' => ['required', 'in:none,stock_limite,promo,stock_epuise,offre_limitee,vote_rayon'],
                'discount' => ['nullable', 'integer', 'in:10,20,50,70']
            ]);
            
            $product = Product::findOrFail($id);
            $product->update([
                'admin_label' => $request->label,
                'admin_label_discount' => $request->discount
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Étiquette appliquée',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'application de l\'étiquette'
            ], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate(['file' => ['required', 'file', 'mimes:csv,xlsx', 'max:10240']]);
            
            $file = $request->file('file');
            $handle = fopen($file->getRealPath(), 'r');
            $headers = fgetcsv($handle);
            $count = 0;
            $errors = [];
            
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = array_combine($headers, $row);
                    if (empty($data['name']) || empty($data['price'])) {
                        $errors[] = "Ligne " . ($count + 2) . ": Nom ou prix manquant";
                        continue;
                    }
                    
                    Product::updateOrCreate(
                        ['sku' => $data['sku'] ?? null],
                        [
                            'name' => $data['name'],
                            'price' => $data['price'],
                            'stock' => $data['stock'] ?? 0,
                            'category_id' => $data['category_id'] ?? 1,
                            'slug' => Str::slug($data['name']) . '-' . Str::random(6),
                            'is_draft' => true
                        ]
                    );
                    $count++;
                } catch (\Exception $e) {
                    $errors[] = "Ligne " . ($count + 2) . ": " . $e->getMessage();
                }
            }
            fclose($handle);
            
            $message = "{$count} produits importés avec succès";
            if (!empty($errors)) {
                $message .= ". Erreurs: " . implode(", ", $errors);
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'import: ' . $e->getMessage()
            ], 500);
        }
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="products.csv"'];
        
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'name', 'sku', 'price', 'compare_price', 'stock', 'category', 'brand', 'is_active', 'is_draft']);
            
            Product::with('category:id,name')->chunk(100, function ($products) use ($handle) {
                foreach ($products as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->name,
                        $p->sku,
                        $p->price,
                        $p->compare_price,
                        $p->stock,
                        $p->category?->name,
                        $p->brand,
                        $p->is_active ? 'oui' : 'non',
                        $p->is_draft ? 'brouillon' : 'publié'
                    ]);
                }
            });
            fclose($handle);
        }, 'products.csv', $headers);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Convertit une valeur en booléen
     */
    private function parseBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) return $default;
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes']);
        }
        return in_array($value, [1, '1', true], true);
    }
}