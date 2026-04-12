<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private CartService  $cartService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->with(['items', 'address', 'delivery'])
            ->latest()
            ->paginate(10);
        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'address_id'         => ['nullable', 'exists:addresses,id'],
            'delivery_type'      => ['required', 'in:home,click_collect,locker'],
            'payment_method'     => ['required', 'in:card,mobile_money,cinetpay,paydunya'],
            'pickup_store'       => ['nullable', 'string'],
            'use_loyalty_points' => ['nullable', 'integer', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cart = $this->cartService->getCart($request);
            $order = $this->orderService->createFromCart($request->user(), $cart, $request->all());
            return response()->json(['message' => 'Commande créée avec succès', 'order' => $order], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->with(['items.product', 'address', 'delivery.events'])->findOrFail($id);
        return response()->json($order);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $order = $request->user()->orders()->findOrFail($id);
        try {
            $this->orderService->cancelOrder($order, $request->reason ?? '');
            return response()->json(['message' => 'Commande annulée avec succès']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reorder(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->with('items.product')->findOrFail($id);
        $cart = $this->cartService->getCart($request);

        $unavailable = [];
        foreach ($order->items as $item) {
            if (!$item->product || !$item->product->is_active) {
                $unavailable[] = $item->product_name;
                continue;
            }
            try {
                $this->cartService->addItem($cart, $item->product_id, $item->quantity);
            } catch (\Exception) {
                $unavailable[] = $item->product_name;
            }
        }

        return response()->json([
            'message'     => 'Produits ajoutés au panier',
            'unavailable' => $unavailable,
        ]);
    }

    public function invoice(Request $request, int $id): mixed
    {
        $order = $request->user()->orders()->with(['items', 'address', 'user'])->findOrFail($id);
        if (!$order->isPaid()) return response()->json(['message' => 'Facture disponible après paiement'], 422);

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);
        return $pdf->download("facture_{$order->order_number}.pdf");
    }
}
