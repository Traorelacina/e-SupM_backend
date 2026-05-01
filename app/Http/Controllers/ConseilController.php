<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Conseil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConseilController extends Controller
{
    /**
     * GET /api/conseils
     * Liste publique avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        $query = Conseil::published()
            ->with('author:id,name,avatar')
            ->orderByDesc('published_at');

        // Filtre catégorie
        if ($request->filled('category') && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        // Filtre type de contenu
        if ($request->filled('content_type')) {
            $query->where('content_type', $request->content_type);
        }

        // Recherche
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('excerpt', 'like', "%{$term}%")
                  ->orWhere('tags', 'like', "%{$term}%");
            });
        }

        // Tri
        match ($request->get('sort', 'recent')) {
            'popular' => $query->orderByDesc('views'),
            'liked'   => $query->orderByDesc('likes'),
            default   => $query->orderByDesc('published_at'),
        };

        $conseils = $query->paginate($request->get('per_page', 12));

        $conseils->getCollection()->transform(fn (Conseil $c) =>
            $c->append(['thumbnail_url', 'tags_array'])
              ->makeHidden(['body', 'recipe_ingredients'])
        );

        // Mise en avant
        $featured = Conseil::published()
            ->featured()
            ->with('author:id,name')
            ->limit(3)
            ->get()
            ->each->append(['thumbnail_url', 'tags_array']);

        return response()->json([
            'data'     => $conseils,
            'featured' => $featured,
        ]);
    }

    /**
     * GET /api/conseils/{slug}
     * Détail public
     */
    public function show(string $slug): JsonResponse
    {
        $conseil = Conseil::published()
            ->where('slug', $slug)
            ->with('author:id,name,avatar')
            ->firstOrFail();

        // Incrémenter les vues (1 seule par session)
        $key = 'viewed_conseil_' . $conseil->id;
        if (! session()->has($key)) {
            $conseil->incrementViews();
            session()->put($key, true);
        }

        $conseil->append(['thumbnail_url', 'tags_array', 'total_time', 'youtube_id']);

        // Conseils similaires
        $related = Conseil::published()
            ->where('id', '!=', $conseil->id)
            ->where('category', $conseil->category)
            ->limit(4)
            ->get()
            ->each->append(['thumbnail_url', 'tags_array'])
            ->makeHidden(['body', 'recipe_ingredients']);

        return response()->json([
            'data'    => $conseil,
            'related' => $related,
        ]);
    }

    /**
     * POST /api/conseils/{conseil}/like
     */
    public function like(Conseil $conseil): JsonResponse
    {
        $key = 'liked_conseil_' . $conseil->id;

        if (session()->has($key)) {
            return response()->json(['message' => 'Déjà liké.', 'likes' => $conseil->likes], 400);
        }

        $conseil->incrementLikes();
        session()->put($key, true);

        return response()->json([
            'message' => 'Merci pour votre like !',
            'likes'   => $conseil->fresh()->likes,
        ]);
    }

    /**
     * GET /api/conseils/categories/stats
     * Statistiques par catégorie pour les onglets
     */
    public function categoryStats(): JsonResponse
    {
        $stats = Conseil::published()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return response()->json([
            'data' => [
                'all'       => Conseil::published()->count(),
                'nutrition' => $stats['nutrition'] ?? 0,
                'astuce'    => $stats['astuce'] ?? 0,
                'recette'   => $stats['recette'] ?? 0,
            ],
        ]);
    }
}