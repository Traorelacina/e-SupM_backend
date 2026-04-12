<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminDeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Delivery::with(['order:id,order_number,user_id','order.user:id,name,phone','driver:id,name,phone']);
        if ($request->status) $q->where('status', $request->status);
        return response()->json($q->latest()->paginate(20));
    }
    public function show(int $id): JsonResponse { return response()->json(Delivery::with(['order.items','order.address','driver','events'])->findOrFail($id)); }
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status'=>['required','string']]);
        Delivery::findOrFail($id)->update(['status'=>$request->status]);
        return response()->json(['message'=>'Statut mis à jour']);
    }
    public function assignDriver(Request $request, int $id): JsonResponse
    {
        $request->validate(['driver_id'=>['required','exists:users,id']]);
        Delivery::findOrFail($id)->update(['driver_id'=>$request->driver_id]);
        return response()->json(['message'=>'Livreur assigné']);
    }
    public function availableDrivers(): JsonResponse
    {
        return response()->json(User::where('role','livreur')->where('status','active')->get(['id','name','phone','avatar']));
    }
}
