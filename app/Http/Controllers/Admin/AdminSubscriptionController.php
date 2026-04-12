<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminSubscriptionController extends Controller
{
    public function __construct(private OrderService $orderService) {}
    public function index(Request $request): JsonResponse
    {
        $q = Subscription::with(['user:id,name,email,phone','items.product']);
        if ($request->status) $q->where('status', $request->status);
        return response()->json($q->latest()->paginate(20));
    }
    public function show(int $id): JsonResponse
    {
        return response()->json(Subscription::with(['user','items.product','address','orders'=>fn($q)=>$q->latest()->take(5)])->findOrFail($id));
    }
    public function update(Request $request, int $id): JsonResponse
    {
        Subscription::findOrFail($id)->update($request->only(['status','frequency','next_delivery_at']));
        return response()->json(['message'=>'Abonnement mis à jour']);
    }
    public function suspend(int $id): JsonResponse
    {
        Subscription::findOrFail($id)->update(['status'=>'suspended']);
        return response()->json(['message'=>'Abonnement suspendu']);
    }
    public function processManually(int $id): JsonResponse
    {
        $sub = Subscription::with(['items.product','user'])->findOrFail($id);
        try {
            $order = $this->orderService->createSubscriptionOrder($sub);
            return response()->json(['message'=>'Commande générée', 'order'=>$order]);
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }
    public function upcoming(Request $request): JsonResponse
    {
        $subs = Subscription::where('status','active')->where('next_delivery_at','<=',now()->addDays(3))->with(['user:id,name,phone','items'])->get();
        return response()->json($subs);
    }
}
