<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodBox;
use App\Models\FoodBoxItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminFoodBoxController extends Controller
{
    // ─── Liste toutes les boxes ──────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $q = FoodBox::with(['items.product.primaryImage', 'items.product.category:id,name']);

        if ($request->q) {
            $q->where('name', 'like', "%{$request->q}%");
        }

        if ($request->active === 'true') {
            $q->where('is_active', true);
        }

        if ($request->featured === 'true') {
            $q->where('is_featured', true);
        }

        if ($request->frequency) {
            $q->where('frequency', $request->frequency);
        }

        $boxes = $q->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $boxes->map(fn($b) => $this->appendImageUrl($b)),
        ]);
    }

    // ─── Détail d'une box ────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $box = FoodBox::with([
            'items' => fn($q) => $q->orderBy('sort_order'),
            'items.product.primaryImage',
            'items.product.category:id,name',
            'items.product.productCategory:id,name,color',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->appendImageUrl($box),
        ]);
    }

    // ─── Créer une box ───────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'name'            => ['required', 'string', 'max:255'],
                'description'     => ['nullable', 'string'],
                'tagline'         => ['nullable', 'string', 'max:255'],
                'price'           => ['required', 'numeric', 'min:0'],
                'compare_price'   => ['nullable', 'numeric', 'min:0'],
                'frequency'       => ['required', 'in:weekly,biweekly,monthly'],
                'is_active'       => ['nullable'],
                'is_featured'     => ['nullable'],
                'max_subscribers' => ['nullable', 'integer', 'min:1'],
                'sort_order'      => ['nullable', 'integer', 'min:0'],
                'badge_label'     => ['nullable', 'string', 'max:50'],
                'badge_color'     => ['nullable', 'string', 'max:20'],
                'image'           => ['nullable', 'image', 'max:5120'],
            ]);

            // Items envoyés séparément (JSON ou form-data)
            $itemsData = $this->parseItems($request);

            $data['is_active']   = $this->parseBool($request->is_active, true);
            $data['is_featured'] = $this->parseBool($request->is_featured, false);
            $data['slug']        = $this->uniqueSlug($data['name']);

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('food-boxes', 'public');
            }

            $box = DB::transaction(function () use ($data, $itemsData) {
                $box = FoodBox::create($data);
                $this->syncItems($box, $itemsData);
                return $box->load(['items.product.primaryImage', 'items.product.category:id,name']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Box alimentaire créée avec succès',
                'data'    => $this->appendImageUrl($box),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création FoodBox: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Mettre à jour une box ───────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $box = FoodBox::findOrFail($id);

            $data = $request->validate([
                'name'            => ['sometimes', 'required', 'string', 'max:255'],
                'description'     => ['nullable', 'string'],
                'tagline'         => ['nullable', 'string', 'max:255'],
                'price'           => ['sometimes', 'required', 'numeric', 'min:0'],
                'compare_price'   => ['nullable', 'numeric', 'min:0'],
                'frequency'       => ['sometimes', 'in:weekly,biweekly,monthly'],
                'is_active'       => ['nullable'],
                'is_featured'     => ['nullable'],
                'max_subscribers' => ['nullable', 'integer', 'min:1'],
                'sort_order'      => ['nullable', 'integer', 'min:0'],
                'badge_label'     => ['nullable', 'string', 'max:50'],
                'badge_color'     => ['nullable', 'string', 'max:20'],
                'image'           => ['nullable', 'image', 'max:5120'],
            ]);

            $itemsData = $this->parseItems($request);

            if ($request->has('is_active'))   $data['is_active']   = $this->parseBool($request->is_active);
            if ($request->has('is_featured')) $data['is_featured'] = $this->parseBool($request->is_featured);

            if ($request->hasFile('image')) {
                if ($box->image) Storage::disk('public')->delete($box->image);
                $data['image'] = $request->file('image')->store('food-boxes', 'public');
            }

            unset($data['slug'], $data['_method']);

            DB::transaction(function () use ($box, $data, $itemsData) {
                $box->update($data);
                // Sync items seulement si envoyés dans la requête
                if ($itemsData !== null) {
                    $this->syncItems($box, $itemsData);
                }
            });

            $box->load(['items.product.primaryImage', 'items.product.category:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Box alimentaire mise à jour',
                'data'    => $this->appendImageUrl($box->fresh(['items.product.primaryImage', 'items.product.category:id,name'])),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur update FoodBox: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Supprimer une box ───────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        try {
            $box = FoodBox::findOrFail($id);

            if ($box->image) {
                Storage::disk('public')->delete($box->image);
            }

            $box->items()->delete();
            $box->delete();

            return response()->json([
                'success' => true,
                'message' => 'Box alimentaire supprimée',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression FoodBox: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    // ─── Toggle actif/inactif ────────────────────────────────────

    public function toggle(int $id): JsonResponse
    {
        try {
            $box       = FoodBox::findOrFail($id);
            $newStatus = !$box->is_active;
            $box->update(['is_active' => $newStatus]);

            return response()->json([
                'success'   => true,
                'is_active' => $newStatus,
                'message'   => $newStatus ? 'Box activée' : 'Box désactivée',
                'data'      => $this->appendImageUrl($box->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
            ], 500);
        }
    }

    // ─── Dupliquer une box ───────────────────────────────────────

    public function duplicate(int $id): JsonResponse
    {
        try {
            $original = FoodBox::with('items')->findOrFail($id);

            $copy = $original->replicate(['subscribers_count']);
            $copy->name      = $original->name . ' (Copie)';
            $copy->slug      = $this->uniqueSlug($copy->name);
            $copy->is_active = false;
            $copy->subscribers_count = 0;
            $copy->save();

            foreach ($original->items as $item) {
                FoodBoxItem::create([
                    'food_box_id' => $copy->id,
                    'product_id'  => $item->product_id,
                    'quantity'    => $item->quantity,
                    'sort_order'  => $item->sort_order,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Box dupliquée avec succès',
                'data'    => $this->appendImageUrl($copy->load(['items.product.primaryImage'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication',
            ], 500);
        }
    }

    // ─── Ajouter / mettre à jour un article de la box ───────────

    public function addItem(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => ['required', 'exists:products,id'],
                'quantity'   => ['required', 'integer', 'min:1'],
            ]);

            $box = FoodBox::findOrFail($id);

            $item = FoodBoxItem::updateOrCreate(
                ['food_box_id' => $box->id, 'product_id' => $request->product_id],
                [
                    'quantity'   => $request->quantity,
                    'sort_order' => $request->sort_order ?? $box->items()->count(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Produit ajouté à la box',
                'data'    => $item->load('product.primaryImage'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Supprimer un article de la box ─────────────────────────

    public function removeItem(int $id, int $itemId): JsonResponse
    {
        try {
            FoodBoxItem::where('food_box_id', $id)->findOrFail($itemId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produit retiré de la box',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    // ─── Recherche de produits (pour le picker) ──────────────────

    public function searchProducts(Request $request): JsonResponse
    {
        $q = Product::with(['primaryImage', 'category:id,name'])
            ->where('is_active', true)
            ->where('is_draft', false);

        if ($request->q) {
            $q->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->q}%")
                      ->orWhere('sku', 'like', "%{$request->q}%");
            });
        }

        if ($request->category_id) {
            $q->where('category_id', $request->category_id);
        }

        $products = $q->limit(20)->get()->map(function ($p) {
            $img = $p->primaryImage;
            $p->thumb_url = $img
                ? (str_starts_with($img->path, 'http') ? $img->path : asset('storage/' . $img->path))
                : null;
            return $p;
        });

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Parse les items depuis la requête (form-data ou JSON)
     * Retourne null si aucun items envoyé (pour le update partiel)
     */
    private function parseItems(Request $request): ?array
    {
        // Cas 1: JSON direct
        if ($request->has('items')) {
            $items = $request->input('items');
            if (is_string($items)) {
                return json_decode($items, true) ?? [];
            }
            return is_array($items) ? $items : null;
        }

        // Cas 2: form-data items[0][product_id], items[0][quantity]…
        $items = [];
        $i     = 0;
        while ($request->has("items[{$i}][product_id]")) {
            $items[] = [
                'product_id' => (int) $request->input("items[{$i}][product_id]"),
                'quantity'   => (int) ($request->input("items[{$i}][quantity]") ?? 1),
                'sort_order' => (int) ($request->input("items[{$i}][sort_order]") ?? $i),
            ];
            $i++;
        }

        return $i > 0 ? $items : null;
    }

    /**
     * Synchronise les items d'une box :
     * supprime les anciens, insère les nouveaux
     */
    private function syncItems(FoodBox $box, ?array $items): void
    {
        if ($items === null) return;

        FoodBoxItem::where('food_box_id', $box->id)->delete();

        foreach ($items as $idx => $item) {
            if (empty($item['product_id'])) continue;

            // Vérifie que le produit existe
            if (!Product::where('id', $item['product_id'])->exists()) continue;

            FoodBoxItem::create([
                'food_box_id' => $box->id,
                'product_id'  => $item['product_id'],
                'quantity'    => max(1, (int) ($item['quantity'] ?? 1)),
                'sort_order'  => (int) ($item['sort_order'] ?? $idx),
            ]);
        }
    }

    private function appendImageUrl(FoodBox $box): FoodBox
    {
        $box->image_url = $box->image
            ? asset('storage/' . $box->image)
            : null;

        return $box;
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
        while (FoodBox::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }
}