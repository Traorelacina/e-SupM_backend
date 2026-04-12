<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryTrackingEvent;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminOrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $q = Order::with(['user:id,name,email,phone','address','items']);
        if ($request->status)   $q->where('status', $request->status);
        if ($request->payment_status) $q->where('payment_status', $request->payment_status);
        if ($request->user_id)  $q->where('user_id', $request->user_id);
        if ($request->date_from)$q->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)  $q->whereDate('created_at', '<=', $request->date_to);
        if ($request->search)   $q->where('order_number','like',"%{$request->search}%");
        if ($request->is_subscription) $q->where('is_subscription_order', true);
        return response()->json($q->latest()->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Order::with(['user','address','items.product','delivery.driver','delivery.events'])->findOrFail($id));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:pending,confirmed,paid,preparing,ready,dispatched,delivered,cancelled,refunded']]);
        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);
        if ($request->status === 'delivered') $order->update(['delivered_at' => now()]);
        $order->user->notify(new \App\Notifications\OrderStatusUpdatedNotification($order));
        return response()->json(['message' => 'Statut mis à jour']);
    }

    public function assignDriver(Request $request, int $id): JsonResponse
    {
        $request->validate(['driver_id' => ['required', 'exists:users,id']]);
        $order = Order::findOrFail($id);
        $delivery = Delivery::firstOrCreate(['order_id' => $id], [
            'driver_id'             => $request->driver_id,
            'status'                => 'assigned',
            'estimated_delivery_at' => now()->addHours(2),
        ]);
        $delivery->update(['driver_id' => $request->driver_id]);
        DeliveryTrackingEvent::create(['delivery_id' => $delivery->id, 'status' => 'assigned', 'message' => 'Livreur assigné à votre commande']);
        $order->update(['status' => 'dispatched', 'tracking_code' => $delivery->tracking_code ?? strtoupper(uniqid('TRK-'))]);
        return response()->json(['message' => 'Livreur assigné', 'delivery' => $delivery]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string']]);
        $order = Order::findOrFail($id);
        if ($order->payment_status !== 'paid') return response()->json(['message' => 'Commande non payée'], 422);
        // In real app, trigger payment gateway refund
        $order->update(['status' => 'refunded', 'payment_status' => 'refunded']);
        return response()->json(['message' => 'Remboursement initié']);
    }

    public function preparatorOrders(Request $request): JsonResponse
    {
        $orders = Order::whereIn('status', ['confirmed','preparing'])
            ->with(['items.product','user:id,name,phone'])
            ->orderByDesc('is_priority')
            ->orderBy('created_at')
            ->paginate(20);
        return response()->json($orders);
    }

    public function startPreparation(int $id): JsonResponse
    {
        Order::findOrFail($id)->update(['status' => 'preparing']);
        return response()->json(['message' => 'Préparation démarrée']);
    }

    public function markReady(int $id): JsonResponse
    {
        Order::findOrFail($id)->update(['status' => 'ready']);
        return response()->json(['message' => 'Commande prête']);
    }

    public function updateItemStatus(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:pending,preparing,ready,substituted,unavailable']]);
        Order::findOrFail($id)->items()->findOrFail($itemId)->update(['preparation_status' => $request->status]);
        return response()->json(['message' => 'Statut article mis à jour']);
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['N°Commande','Client','Email','Total','Statut Paiement','Statut','Date','Livraison']);
            Order::with('user')->chunk(100, function($orders) use ($handle) {
                foreach ($orders as $o) {
                    fputcsv($handle, [$o->order_number, $o->user->name, $o->user->email, $o->total, $o->payment_status, $o->status, $o->created_at->format('d/m/Y H:i'), $o->delivery_type]);
                }
            });
            fclose($handle);
        }, 'commandes.csv', ['Content-Type'=>'text/csv']);
    }
}
