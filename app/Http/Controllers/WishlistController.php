<?php
namespace App\Http\Controllers;
use App\Models\Wishlist;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index(Request $request): JsonResponse
    {
        $wishlist = $request->user()->wishlist()->with('product.primaryImage')->latest()->get();
        return response()->json($wishlist);
    }

    public function add(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'exists:products,id']]);
        Wishlist::firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $request->product_id]);
        return response()->json(['message' => 'Ajouté aux favoris'], 201);
    }

    public function remove(Request $request, int $productId): JsonResponse
    {
        $request->user()->wishlist()->where('product_id', $productId)->delete();
        return response()->json(['message' => 'Retiré des favoris']);
    }

    public function moveToCart(Request $request): JsonResponse
    {
        $request->validate(['product_ids' => ['required', 'array']]);
        $cart  = $this->cartService->getCart($request);
        $moved = [];
        foreach ($request->product_ids as $productId) {
            try {
                $this->cartService->addItem($cart, $productId, 1);
                $request->user()->wishlist()->where('product_id', $productId)->delete();
                $moved[] = $productId;
            } catch (\Exception) {}
        }
        return response()->json(['message' => count($moved) . ' produit(s) déplacé(s) vers le panier', 'moved' => $moved]);
    }
}
