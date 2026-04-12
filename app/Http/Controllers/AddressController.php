<?php
namespace App\Http\Controllers;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->addresses()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'label'          => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'max:20'],
            'address_line1'  => ['required', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:100'],
            'district'       => ['nullable', 'string'],
            'latitude'       => ['nullable', 'numeric'],
            'longitude'      => ['nullable', 'numeric'],
            'is_default'     => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('is_default')) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $address = $request->user()->addresses()->create($request->validated());
        return response()->json($address, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($request->user()->addresses()->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->update($request->only(['label','recipient_name','phone','address_line1','address_line2','city','district','latitude','longitude']));
        return response()->json($address);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->addresses()->findOrFail($id)->delete();
        return response()->json(['message' => 'Adresse supprimée']);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $request->user()->addresses()->update(['is_default' => false]);
        $request->user()->addresses()->findOrFail($id)->update(['is_default' => true]);
        return response()->json(['message' => 'Adresse par défaut mise à jour']);
    }
}
