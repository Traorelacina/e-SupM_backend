<?php
namespace App\Http\Controllers;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request);
        $summary = $this->cartService->getCartSummary($cart);
        return response()->json(['cart' => $cart, 'summary' => $summary]);
    }

    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:100'],
            'size'       => ['nullable', 'string'],
            'color'      => ['nullable', 'string'],
        ]);
        try {
            $cart = $this->cartService->getCart($request);
            $item = $this->cartService->addItem($cart, $request->product_id, $request->quantity, $request->size, $request->color);
            return response()->json(['message' => 'Produit ajouté au panier', 'item' => $item->load('product.primaryImage')], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        try {
            $cart = $this->cartService->getCart($request);
            $item = $this->cartService->updateItem($cart, $id, $request->quantity);
            return response()->json(['message' => 'Quantité mise à jour', 'item' => $item]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function removeItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->cartService->getCart($request);
        $this->cartService->removeItem($cart, $id);
        return response()->json(['message' => 'Produit retiré du panier']);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request);
        $this->cartService->clearCart($cart);
        return response()->json(['message' => 'Panier vidé']);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        try {
            $cart = $this->cartService->getCart($request);
            $userId = auth()->id() ?? 0;
            $result = $this->cartService->applyCoupon($cart, $request->code, $userId);
            return response()->json(['message' => 'Code promo appliqué !', ...$result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request);
        $this->cartService->removeCoupon($cart);
        return response()->json(['message' => 'Code promo retiré']);
    }
}
