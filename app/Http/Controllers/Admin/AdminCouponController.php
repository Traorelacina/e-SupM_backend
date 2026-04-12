<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminCouponController extends Controller
{
    public function index(): JsonResponse { return response()->json(Coupon::withCount('usages')->latest()->paginate(20)); }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['code'=>['nullable','string','unique:coupons,code'],'discount_type'=>['required','in:percentage,fixed'],'discount_value'=>['required','numeric'],'min_purchase'=>['nullable','numeric'],'max_discount'=>['nullable','numeric'],'max_uses'=>['nullable','integer'],'max_uses_per_user'=>['nullable','integer'],'expires_at'=>['nullable','date'],'is_first_order_only'=>['nullable','boolean']]);
        $data['code'] = strtoupper($data['code'] ?? Str::random(8));
        return response()->json(Coupon::create($data), 201);
    }
    public function show(int $id): JsonResponse { return response()->json(Coupon::withCount('usages')->findOrFail($id)); }
    public function update(Request $request, int $id): JsonResponse { Coupon::findOrFail($id)->update($request->except('code')); return response()->json(['message'=>'Coupon mis à jour']); }
    public function destroy(int $id): JsonResponse { Coupon::findOrFail($id)->delete(); return response()->json(['message'=>'Coupon supprimé']); }
    public function generateBulk(Request $request): JsonResponse
    {
        $request->validate(['count'=>['required','integer','min:1','max:500'],'prefix'=>['nullable','string'],'discount_type'=>['required','in:percentage,fixed'],'discount_value'=>['required','numeric']]);
        $codes = [];
        for ($i=0; $i<$request->count; $i++) {
            $code = strtoupper(($request->prefix ?? '') . Str::random(8));
            $codes[] = Coupon::create(['code'=>$code,'discount_type'=>$request->discount_type,'discount_value'=>$request->discount_value,'max_uses'=>1,'expires_at'=>$request->expires_at]);
        }
        return response()->json(['message'=>"{$request->count} coupons créés", 'count'=>count($codes)]);
    }
    public function usages(int $id): JsonResponse { return response()->json(Coupon::findOrFail($id)->usages()->with(['user:id,name,email','order:id,order_number'])->paginate(20)); }
}
