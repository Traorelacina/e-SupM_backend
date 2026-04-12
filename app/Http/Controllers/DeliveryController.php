<?php
namespace App\Http\Controllers;
use App\Models\Delivery;
use App\Models\DeliveryTrackingEvent;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    public function track(Request $request, int $orderId): JsonResponse
    {
        $order    = $request->user()->orders()->findOrFail($orderId);
        $delivery = Delivery::where('order_id', $orderId)->with(['driver:id,name,phone,avatar', 'events'])->first();
        if (!$delivery) return response()->json(['message' => 'Livraison non encore assignée', 'order_status' => $order->status]);
        return response()->json($delivery);
    }

    public function status(Request $request, int $orderId): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($orderId);
        return response()->json(['status' => $order->status, 'delivery_status' => $order->delivery?->status]);
    }

    // DRIVER routes
    public function driverDeliveries(Request $request): JsonResponse
    {
        $deliveries = Delivery::where('driver_id', $request->user()->id)
            ->whereIn('status', ['assigned','picked_up','in_transit'])
            ->with(['order.address','order.user:id,name,phone'])->get();
        return response()->json($deliveries);
    }

    public function driverDeliveryDetail(Request $request, int $id): JsonResponse
    {
        $delivery = Delivery::where('driver_id', $request->user()->id)->with(['order.items','order.address'])->findOrFail($id);
        return response()->json($delivery);
    }

    public function pickup(Request $request, int $id): JsonResponse
    {
        $delivery = Delivery::where('driver_id', $request->user()->id)->findOrFail($id);
        $delivery->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        DeliveryTrackingEvent::create(['delivery_id' => $delivery->id, 'status' => 'picked_up', 'message' => 'Commande récupérée par le livreur']);
        return response()->json(['message' => 'Commande récupérée']);
    }

    public function delivered(Request $request, int $id): JsonResponse
    {
        $request->validate(['proof_image' => ['nullable', 'image', 'max:5120'], 'recipient_name' => ['nullable', 'string']]);
        $delivery = Delivery::where('driver_id', $request->user()->id)->findOrFail($id);
        $proof    = $request->file('proof_image')?->store('delivery-proofs', 'public');
        $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'delivery_proof_image' => $proof, 'recipient_name' => $request->recipient_name]);
        DeliveryTrackingEvent::create(['delivery_id' => $delivery->id, 'status' => 'delivered', 'message' => 'Commande livrée avec succès']);
        $delivery->order->update(['status' => 'delivered', 'delivered_at' => now()]);
        return response()->json(['message' => 'Livraison confirmée']);
    }

    public function updateLocation(Request $request, int $id): JsonResponse
    {
        $request->validate(['latitude' => ['required', 'numeric'], 'longitude' => ['required', 'numeric']]);
        $delivery = Delivery::where('driver_id', $request->user()->id)->findOrFail($id);
        $delivery->update(['driver_latitude' => $request->latitude, 'driver_longitude' => $request->longitude]);
        return response()->json(['message' => 'Position mise à jour']);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:in_transit,arrived,failed']]);
        $delivery = Delivery::where('driver_id', $request->user()->id)->findOrFail($id);
        $delivery->update(['status' => $request->status]);
        $messages = ['in_transit' => 'En route vers vous', 'arrived' => 'Livreur arrivé à destination', 'failed' => 'Tentative de livraison échouée'];
        DeliveryTrackingEvent::create(['delivery_id' => $delivery->id, 'status' => $request->status, 'message' => $messages[$request->status] ?? $request->status]);
        return response()->json(['message' => 'Statut mis à jour']);
    }
}
