<?php
namespace App\Http\Controllers;
use App\Models\DelegateShoppingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DelegateShoppingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->delegateShoppingRequests()->latest()->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'list_text'       => ['nullable', 'string', 'max:2000'],
            'list_image'      => ['nullable', 'image', 'max:5120'],
            'list_audio'      => ['nullable', 'file', 'mimes:mp3,ogg,m4a,wav', 'max:10240'],
            'delivery_type'   => ['required', 'in:home,store_koumassi'],
            'address_id'      => ['nullable', 'exists:addresses,id'],
            'recipient_name'  => ['nullable', 'string'],
            'recipient_phone' => ['nullable', 'string'],
            'notes'           => ['nullable', 'string'],
        ]);

        $image = $request->file('list_image')?->store('delegate-lists', 'public');
        $audio = $request->file('list_audio')?->store('delegate-audio', 'public');

        $req = DelegateShoppingRequest::create([
            'user_id'         => $request->user()->id,
            'list_text'       => $request->list_text,
            'list_image'      => $image,
            'list_audio'      => $audio,
            'delivery_type'   => $request->delivery_type,
            'address_id'      => $request->address_id,
            'recipient_name'  => $request->recipient_name,
            'recipient_phone' => $request->recipient_phone,
            'notes'           => $request->notes,
        ]);

        return response()->json(['message' => 'Demande de courses déléguées envoyée !', 'request' => $req], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($request->user()->delegateShoppingRequests()->findOrFail($id));
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $requests = DelegateShoppingRequest::with(['user:id,name,phone', 'address'])->latest()->paginate(20);
        return response()->json($requests);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:received,processing,ready,delivered,cancelled'], 'estimated_amount' => ['nullable', 'numeric'], 'final_amount' => ['nullable', 'numeric']]);
        DelegateShoppingRequest::findOrFail($id)->update($request->only(['status', 'estimated_amount', 'final_amount', 'notes']));
        return response()->json(['message' => 'Statut mis à jour']);
    }
}
