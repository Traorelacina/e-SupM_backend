<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminPromotionController extends Controller
{
    public function index(): JsonResponse { return response()->json(Promotion::with(['category','product'])->latest()->paginate(20)); }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name'=>['required','string'],'type'=>['required','string'],'discount_type'=>['required','string'],'discount_value'=>['required','numeric'],'scope'=>['nullable','string'],'category_id'=>['nullable','exists:categories,id'],'product_id'=>['nullable','exists:products,id'],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after:starts_at'],'min_purchase'=>['nullable','numeric'],'is_flash'=>['nullable','boolean']]);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('promotions','public');
        return response()->json(Promotion::create($data), 201);
    }
    public function show(int $id): JsonResponse { return response()->json(Promotion::findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Promotion::findOrFail($id)->update($request->all()); return response()->json(['message'=>'Promo mise à jour']); }
    public function destroy(int $id): JsonResponse { Promotion::findOrFail($id)->delete(); return response()->json(['message'=>'Promo supprimée']); }
    public function toggle(int $id): JsonResponse { $p = Promotion::findOrFail($id); $p->update(['is_active'=>!$p->is_active]); return response()->json(['is_active'=>$p->is_active]); }
}
