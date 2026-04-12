<?php
namespace App\Http\Controllers;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recipes = Recipe::where('is_published', true)
            ->when($request->category, fn($q,$c) => $q->where('category',$c))
            ->with('author:id,name,avatar')
            ->latest()->paginate(12);
        return response()->json($recipes);
    }
    public function show(int $id): JsonResponse
    {
        $recipe = Recipe::where('is_published', true)->with('author:id,name,avatar')->findOrFail($id);
        $recipe->increment('views_count');
        return response()->json($recipe);
    }
}
