<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Models\Game;
use App\Models\CharityDonation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminStatsController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->subMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());
        $data = Order::where('payment_status','paid')->whereBetween('created_at',[$from,$to])
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders, AVG(total) as avg_basket')
            ->groupBy('date')->orderBy('date')->get();
        return response()->json($data);
    }

    public function orders(Request $request): JsonResponse
    {
        return response()->json([
            'by_status'        => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->get(),
            'by_payment'       => Order::selectRaw('payment_status, COUNT(*) as count')->groupBy('payment_status')->get(),
            'by_delivery_type' => Order::selectRaw('delivery_type, COUNT(*) as count')->groupBy('delivery_type')->get(),
        ]);
    }

    public function products(): JsonResponse
    {
        return response()->json([
            'top_sellers'    => \App\Models\OrderItem::selectRaw('product_name, SUM(quantity) as sold')->groupBy('product_name')->orderByDesc('sold')->take(10)->get(),
            'top_revenue'    => \App\Models\OrderItem::selectRaw('product_name, SUM(total) as revenue')->groupBy('product_name')->orderByDesc('revenue')->take(10)->get(),
            'low_stock'      => Product::lowStock()->count(),
            'out_of_stock'   => Product::outOfStock()->count(),
            'active_count'   => Product::active()->count(),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'by_role'  => User::selectRaw('role, COUNT(*) as count')->groupBy('role')->get(),
            'by_level' => User::selectRaw('loyalty_level, COUNT(*) as count')->groupBy('loyalty_level')->get(),
            'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'total' => User::count(),
        ]);
    }

    public function games(): JsonResponse
    {
        return response()->json([
            'total_games'        => Game::count(),
            'active_games'       => Game::active()->count(),
            'total_participations'=> \App\Models\GameParticipant::count(),
            'total_winners'      => \App\Models\GameParticipant::where('is_winner',true)->count(),
            'by_type'            => Game::selectRaw('type, COUNT(*) as count')->groupBy('type')->get(),
        ]);
    }

    public function charity(): JsonResponse
    {
        return response()->json([
            'total_donated'   => CharityDonation::where('status','confirmed')->sum('amount'),
            'donations_count' => CharityDonation::count(),
            'confirmed_count' => CharityDonation::where('status','confirmed')->count(),
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $monthly = Order::where('payment_status','paid')->whereYear('created_at',$year)
            ->selectRaw('MONTH(created_at) as month, SUM(total) as revenue, COUNT(*) as orders')->groupBy('month')->get();
        return response()->json(['year'=>$year,'monthly'=>$monthly,'total'=>$monthly->sum('revenue')]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output','w');
            fputcsv($handle, ['Métrique','Valeur']);
            fputcsv($handle, ['Chiffre d\'affaires total', Order::where('payment_status','paid')->sum('total')]);
            fputcsv($handle, ['Nombre de commandes', Order::count()]);
            fputcsv($handle, ['Panier moyen', Order::where('payment_status','paid')->avg('total')]);
            fputcsv($handle, ['Clients actifs', User::where('role','client')->where('status','active')->count()]);
            fputcsv($handle, ['Produits actifs', Product::active()->count()]);
            fclose($handle);
        }, 'stats.csv', ['Content-Type'=>'text/csv']);
    }
}
