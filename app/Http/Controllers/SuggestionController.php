<?php
namespace App\Http\Controllers;
use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SuggestionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['category' => ['nullable','string'], 'message' => ['required','string','max:1000']]);
        $suggestion = Suggestion::create(['user_id' => $request->user()->id, 'category' => $request->category, 'message' => $request->message]);
        return response()->json(['message' => 'Suggestion soumise, merci !', 'suggestion' => $suggestion], 201);
    }

    public function my(Request $request): JsonResponse
    {
        return response()->json($request->user()->suggestions()->latest()->get());
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $suggestions = Suggestion::with('user:id,name,email')->latest()->paginate(20);
        return response()->json($suggestions);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:new,reviewed,implemented,rejected']]);
        Suggestion::findOrFail($id)->update(['status' => $request->status]);
        return response()->json(['message' => 'Statut mis à jour']);
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $request->validate(['response' => ['required', 'string']]);
        $s = Suggestion::findOrFail($id);
        $s->update(['admin_response' => $request->response, 'status' => 'reviewed']);
        return response()->json(['message' => 'Réponse envoyée']);
    }
}
