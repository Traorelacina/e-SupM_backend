<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminRecipeController extends Controller
{
    public function index(): JsonResponse { return response()->json(Recipe::with('author:id,name')->latest()->paginate(20)); }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title'=>['required','string'],'description'=>['nullable','string'],'ingredients'=>['required','array'],'steps'=>['required','array'],'prep_time_minutes'=>['nullable','integer'],'cook_time_minutes'=>['nullable','integer'],'servings'=>['nullable','integer'],'difficulty'=>['nullable','string'],'category'=>['nullable','string']]);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = Str::slug($data['title']) . '-' . Str::random(4);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('recipes','public');
        return response()->json(Recipe::create($data), 201);
    }
    public function show(int $id): JsonResponse { return response()->json(Recipe::findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Recipe::findOrFail($id)->update($request->except('slug')); return response()->json(['message'=>'Recette mise à jour']); }
    public function destroy(int $id): JsonResponse { Recipe::findOrFail($id)->delete(); return response()->json(['message'=>'Recette supprimée']); }
    public function publish(int $id): JsonResponse { $r = Recipe::findOrFail($id); $r->update(['is_published'=>!$r->is_published]); return response()->json(['is_published'=>$r->is_published]); }
}
