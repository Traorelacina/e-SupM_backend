<?php
namespace App\Http\Controllers;
use App\Models\CharityDonation;
use App\Models\CharityVoucher;
use App\Models\Product;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CharityController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function myDonations(Request $request): JsonResponse
    {
        $donations = $request->user()->charityDonations()->with(['product','vouchers'])->latest()->paginate(10);
        return response()->json($donations);
    }

    public function donateVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'amount'         => ['required', 'numeric', 'min:500'],
            'payment_method' => ['required', 'in:mobile_money,virement,card'],
        ]);

        return DB::transaction(function () use ($request) {
            $user     = $request->user();
            $amount   = $request->amount;

            $donation = CharityDonation::create([
                'user_id'         => $user->id,
                'type'            => 'voucher',
                'amount'          => $amount,
                'payment_method'  => $request->payment_method,
                'status'          => 'pending',
            ]);

            // Generate voucher code
            $voucher = CharityVoucher::create([
                'code'        => 'CHR-' . strtoupper(Str::random(8)),
                'donation_id' => $donation->id,
                'amount'      => $amount,
                'expires_at'  => now()->addYear(),
            ]);

            // Award loyalty points (social badge)
            $points = (int)($amount / 500) * 10; // 10pts per 500 FCFA
            $this->loyaltyService->awardPoints($user, $points, 'bonus', "Don alimentaire: {$amount} FCFA");
            $donation->update(['loyalty_points_earned' => $points]);

            // Unlock scratch card if >= 5000
            if ($amount >= 5000) {
                $donation->update(['scratch_card_unlocked' => true]);
            }

            $this->loyaltyService->checkAndAwardBadges($user);

            return response()->json(['message' => 'Merci pour votre don !', 'donation' => $donation->load('vouchers'), 'points_earned' => $points], 201);
        });
    }

    public function donateProduct(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $product  = Product::findOrFail($request->product_id);
        $user     = $request->user();
        $amount   = $product->price * $request->quantity;

        $donation = CharityDonation::create([
            'user_id'    => $user->id,
            'type'       => 'product',
            'product_id' => $product->id,
            'quantity'   => $request->quantity,
            'amount'     => $amount,
            'status'     => 'pending',
        ]);

        $points = (int)($amount / 500) * 10;
        $this->loyaltyService->awardPoints($user, $points, 'bonus', "Don produit: {$product->name} x{$request->quantity}");
        $donation->update(['loyalty_points_earned' => $points]);
        if ($amount >= 5000) $donation->update(['scratch_card_unlocked' => true]);

        return response()->json(['message' => 'Don de produit enregistré ! Merci.', 'donation' => $donation, 'points_earned' => $points], 201);
    }

    public function checkVoucher(string $code): JsonResponse
    {
        $voucher = CharityVoucher::where('code', strtoupper($code))->with('donation')->first();
        if (!$voucher) return response()->json(['message' => 'Bon invalide'], 404);
        if ($voucher->is_used) return response()->json(['message' => 'Ce bon a déjà été utilisé', 'used_at' => $voucher->used_at], 422);
        if ($voucher->expires_at && $voucher->expires_at->isPast()) return response()->json(['message' => 'Ce bon est expiré'], 422);
        return response()->json(['voucher' => $voucher, 'valid' => true]);
    }

    public function impact(Request $request): JsonResponse
    {
        $user = $request->user();
        $totalDonated  = $user->charityDonations()->where('status', 'confirmed')->sum('amount');
        $donationsCount= $user->charityDonations()->where('status', 'confirmed')->count();
        $productsCount = $user->charityDonations()->where('type', 'product')->where('status', 'confirmed')->sum('quantity');

        return response()->json([
            'total_donated'   => $totalDonated,
            'donations_count' => $donationsCount,
            'products_gifted' => $productsCount,
            'message'         => "Grâce à vous, {$donationsCount} familles ont bénéficié de votre générosité !",
        ]);
    }
}
