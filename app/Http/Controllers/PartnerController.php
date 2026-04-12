<?php
namespace App\Http\Controllers;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Partner::where('status','approved')->where('show_on_homepage', true)->orderBy('sort_order')->get());
    }
    public function show(int $id): JsonResponse
    {
        return response()->json(Partner::where('status','approved')->findOrFail($id));
    }
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'company_name'  => ['required', 'string', 'max:200'],
            'contact_name'  => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email'],
            'phone'         => ['required', 'string'],
            'address'       => ['required', 'string'],
            'type'          => ['required', 'in:supplier,delivery,advertiser,producer'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'proof_images'  => ['nullable', 'array', 'max:5'],
            'proof_images.*'=> ['image', 'max:5120'],
        ]);
        $images = [];
        foreach ($request->file('proof_images', []) as $file) {
            $images[] = $file->store('partner-proofs', 'public');
        }
        $partner = Partner::create([...$request->only(['company_name','contact_name','email','phone','address','type','description']), 'proof_images' => $images, 'user_id' => auth()->id()]);
        return response()->json(['message' => 'Candidature envoyée, en attente de validation.', 'partner' => $partner], 201);
    }
}
