<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConseilRequest;
use App\Models\Conseil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConseilController extends Controller
{
    /**
     * GET /admin/conseils
     * Liste paginée avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        $query = Conseil::with('author:id,name,avatar')
            ->orderByDesc('created_at');

        // Filtres
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('content_type')) {
            $query->where('content_type', $request->content_type);
        }
        if ($request->filled('is_published')) {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('is_featured')) {
            $query->where('is_featured', true);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('excerpt', 'like', '%' . $request->search . '%')
                  ->orWhere('tags', 'like', '%' . $request->search . '%');
            });
        }

        $conseils = $query->paginate($request->get('per_page', 15));

        // Append thumbnail_url
        $conseils->getCollection()->transform(function (Conseil $c) {
            $c->append('thumbnail_url');
            return $c;
        });

        // Stats globales
        $stats = [
            'total'       => Conseil::count(),
            'published'   => Conseil::where('is_published', true)->count(),
            'drafts'      => Conseil::where('is_published', false)->count(),
            'featured'    => Conseil::where('is_featured', true)->count(),
            'by_category' => Conseil::selectRaw('category, COUNT(*) as count')
                                    ->groupBy('category')
                                    ->pluck('count', 'category'),
        ];

        return response()->json([
            'data'  => $conseils,
            'stats' => $stats,
        ]);
    }

    /**
     * POST /admin/conseils
     */
    public function store(ConseilRequest $request): JsonResponse
    {
        $data = $this->prepareData($request);
        $data['author_id'] = $request->user()->id;

        $conseil = Conseil::create($data);
        $conseil->append('thumbnail_url');

        return response()->json([
            'message' => 'Conseil créé avec succès.',
            'data'    => $conseil->load('author:id,name'),
        ], 201);
    }

    /**
     * GET /admin/conseils/{conseil}
     */
    public function show(Conseil $conseil): JsonResponse
    {
        $conseil->load('author:id,name,avatar');
        $conseil->append(['thumbnail_url', 'tags_array', 'total_time', 'youtube_id']);

        return response()->json(['data' => $conseil]);
    }

    /**
     * PUT/PATCH /admin/conseils/{conseil}
     */
    public function update(ConseilRequest $request, Conseil $conseil): JsonResponse
    {
        $data = $this->prepareData($request, $conseil);
        $conseil->update($data);
        $conseil->append('thumbnail_url');

        return response()->json([
            'message' => 'Conseil mis à jour.',
            'data'    => $conseil->fresh()->load('author:id,name'),
        ]);
    }

    /**
     * DELETE /admin/conseils/{conseil}
     */
    public function destroy(Conseil $conseil): JsonResponse
    {
        // Suppression de la miniature si locale
        if ($conseil->thumbnail && ! str_starts_with($conseil->thumbnail, 'http')) {
            Storage::disk('public')->delete($conseil->thumbnail);
        }

        $conseil->delete();

        return response()->json(['message' => 'Conseil supprimé.']);
    }

    /**
     * PATCH /admin/conseils/{conseil}/toggle-publish
     */
    public function togglePublish(Conseil $conseil): JsonResponse
    {
        $conseil->update([
            'is_published' => ! $conseil->is_published,
            'published_at' => ! $conseil->is_published ? now() : $conseil->published_at,
        ]);

        return response()->json([
            'message'      => $conseil->is_published ? 'Conseil publié.' : 'Conseil dépublié.',
            'is_published' => $conseil->is_published,
        ]);
    }

    /**
     * PATCH /admin/conseils/{conseil}/toggle-featured
     */
    public function toggleFeatured(Conseil $conseil): JsonResponse
    {
        $conseil->update(['is_featured' => ! $conseil->is_featured]);

        return response()->json([
            'message'     => $conseil->is_featured ? 'Mis en avant.' : 'Retiré de la mise en avant.',
            'is_featured' => $conseil->is_featured,
        ]);
    }

    /**
     * POST /admin/conseils/upload-media
     * Upload d'image (thumbnail ou galerie)
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'type' => ['nullable', 'in:thumbnail,gallery'],
        ]);

        $path = $request->file('file')->store(
            'conseils/' . ($request->type ?? 'gallery'),
            'public'
        );

        return response()->json([
            'path' => $path,
            'url'  => asset('storage/' . $path),
        ]);
    }

    /**
     * DELETE /admin/conseils/delete-media
     */
    public function deleteMedia(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        if (! str_starts_with($request->path, 'http')) {
            Storage::disk('public')->delete($request->path);
        }

        return response()->json(['message' => 'Fichier supprimé.']);
    }

    // ── Helpers privés ────────────────────────────────────────────

    private function prepareData(ConseilRequest $request, ?Conseil $existing = null): array
    {
        $data = $request->except(['thumbnail_file', '_method']);

        // Cast booléens
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured']  = $request->boolean('is_featured');

        // Auto-slug
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Upload miniature
        if ($request->hasFile('thumbnail_file')) {
            // Supprimer l'ancienne
            if ($existing && $existing->thumbnail && ! str_starts_with($existing->thumbnail, 'http')) {
                Storage::disk('public')->delete($existing->thumbnail);
            }
            $data['thumbnail'] = $request->file('thumbnail_file')
                ->store('conseils/thumbnails', 'public');
        }

        // Temps de lecture auto (si body fourni)
        if (!empty($data['body']) && empty($data['reading_time'])) {
            $words = str_word_count(strip_tags($data['body']));
            $data['reading_time'] = max(1, (int) ceil($words / 200)) . ' min';
        }

        // Détecter le provider vidéo
        if (!empty($data['video_url']) && empty($data['video_provider'])) {
            $data['video_provider'] = match(true) {
                str_contains($data['video_url'], 'youtube') => 'youtube',
                str_contains($data['video_url'], 'youtu.be') => 'youtube',
                str_contains($data['video_url'], 'vimeo') => 'vimeo',
                default => 'local',
            };
        }

        return $data;
    }
}