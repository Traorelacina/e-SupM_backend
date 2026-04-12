<?php
namespace App\Http\Controllers;
use App\Models\Review;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function index(int $id): JsonResponse
    {
        $reviews = Review::where('product_id', $id)->where('is_approved', true)->with('user:id,name,avatar')->latest()->paginate(10);
        return response()->json($reviews);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rating'   => ['required', 'integer', 'between:1,5'],
            'comment'  => ['nullable', 'string', 'max:1000'],
            'images'   => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'max:2048'],
            'order_id' => ['nullable', 'exists:orders,id'],
        ]);

        // Check user bought this product
        $hasBought = $request->user()->orders()->where('payment_status','paid')->whereHas('items', fn($q) => $q->where('product_id', $id))->exists();
        if (!$hasBought) return response()->json(['message' => 'Vous devez avoir acheté ce produit pour laisser un avis'], 403);

        $existing = Review::where(['user_id' => $request->user()->id, 'product_id' => $id])->first();
        if ($existing) return response()->json(['message' => 'Vous avez déjà laissé un avis pour ce produit'], 422);

        $images = [];
        foreach ($request->file('images', []) as $file) {
            $images[] = $file->store('reviews', 'public');
        }

        $review = Review::create([
            'user_id'    => $request->user()->id,
            'product_id' => $id,
            'order_id'   => $request->order_id,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
            'images'     => $images,
        ]);

        // Update product average rating
        $avg = Review::where('product_id', $id)->where('is_approved', true)->avg('rating');
        $count = Review::where('product_id', $id)->where('is_approved', true)->count();
        \App\Models\Product::where('id', $id)->update(['average_rating' => $avg, 'reviews_count' => $count]);

        // Award loyalty points for review
        $this->loyaltyService->awardPoints($request->user(), 20, 'review', "Avis laissé sur un produit");
        $review->update(['loyalty_points_earned' => 20]);

        return response()->json(['message' => 'Avis soumis, en attente de validation', 'review' => $review], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)->findOrFail($id);
        $request->validate(['rating' => ['nullable', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $review->update($request->only(['rating', 'comment']));
        return response()->json(['message' => 'Avis modifié', 'review' => $review]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Review::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Avis supprimé']);
    }
}
