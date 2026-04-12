<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    /**
     * Liste toutes les catégories avec leurs enfants (arbre).
     * Inclut les catégories inactives pour l'administration.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($cat) => $this->appendImageUrl($cat));

        return response()->json($categories);
    }

    /**
     * Arbre complet — même résultat que index().
     * IMPORTANT : cette route doit être déclarée AVANT la route {id}
     * dans api.php pour ne pas être capturée par show().
     */
    public function tree(): JsonResponse
    {
        return $this->index();
    }

    /**
     * Détail d'une catégorie.
     * Le paramètre est mixed pour éviter un TypeError si Laravel route
     * accidentellement une string ici — on valide manuellement.
     */
    public function show(mixed $id): JsonResponse
    {
        // Sécurité : si $id n'est pas numérique, renvoyer 404 proprement
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }

        $cat = Category::with(['children', 'parent'])->findOrFail((int) $id);
        return response()->json($this->appendImageUrl($cat));
    }

    /**
     * Créer une catégorie.
     */
    public function store(Request $request): JsonResponse
    {
        try {
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

            return response()->json($this->appendImageUrl($cat), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour une catégorie.
     */
    public function update(Request $request, mixed $id): JsonResponse
    {
        try {
            $cat = Category::findOrFail((int) $id);

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
            $cat->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Rayon mis à jour',
                'data'    => $this->appendImageUrl($cat),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer une catégorie.
     */
    public function destroy(mixed $id): JsonResponse
    {
        try {
            $cat = Category::findOrFail((int) $id);

            if ($cat->image) {
                Storage::disk('public')->delete($cat->image);
            }

            $cat->delete();

            return response()->json(['success' => true, 'message' => 'Rayon supprimé']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Changer l'ordre d'affichage.
     */
    public function reorder(Request $request, mixed $id): JsonResponse
    {
        $request->validate(['sort_order' => ['required', 'integer', 'min:0']]);
        Category::findOrFail((int) $id)->update(['sort_order' => $request->sort_order]);
        return response()->json(['success' => true, 'message' => 'Ordre mis à jour']);
    }

    /**
     * Activer / désactiver une catégorie.
     */
    public function toggle(mixed $id): JsonResponse
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

    // ── Helpers ────────────────────────────────────────────────────

    private function appendImageUrl(Category $cat): Category
    {
        $cat->image_url = $cat->image ? asset('storage/' . $cat->image) : null;

        if ($cat->relationLoaded('children') && $cat->children) {
            $cat->setRelation('children', $cat->children->map(
                fn($child) => $this->appendImageUrl($child)
            ));
        }

        return $cat;
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
}