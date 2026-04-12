<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth();

        return response()->json([
            // Chiffres du jour
            'today' => [
                'orders'   => Order::whereDate('created_at', $today)->count(),
                'revenue'  => Order::whereDate('created_at', $today)->where('payment_status', 'paid')->sum('total'),
                'new_users'=> User::whereDate('created_at', $today)->count(),
            ],
            // Ce mois
            'month' => [
                'orders'      => Order::where('created_at', '>=', $thisMonth)->count(),
                'revenue'     => Order::where('created_at', '>=', $thisMonth)->where('payment_status', 'paid')->sum('total'),
                'new_users'   => User::where('created_at', '>=', $thisMonth)->count(),
                'avg_basket'  => Order::where('created_at', '>=', $thisMonth)->where('payment_status', 'paid')->avg('total'),
            ],
            // Totaux
            'totals' => [
                'users'         => User::where('role', 'client')->count(),
                'products'      => Product::active()->count(),
                'orders'        => Order::count(),
                'subscriptions' => Subscription::where('status', 'active')->count(),
            ],
            // Dernières commandes
            'recent_orders' => Order::with(['user:id,name,email', 'items'])->latest()->take(10)->get(),
            // Produits en rupture ou faible stock
            'low_stock_count'    => Product::lowStock()->count(),
            'out_of_stock_count' => Product::outOfStock()->count(),
            // Commandes en attente de préparation
            'pending_preparation' => Order::where('status', 'confirmed')->count(),
        ]);
    }

    public function kpis(): JsonResponse
    {
        $months = collect(range(5, 0))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            return [
                'month'   => $date->format('M Y'),
                'revenue' => Order::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->where('payment_status', 'paid')
                    ->sum('total'),
                'orders'  => Order::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
                'users'   => User::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        });

        $topProducts = \App\Models\OrderItem::select('product_name')
            ->selectRaw('SUM(quantity) as total_sold, SUM(total) as total_revenue')
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->take(10)->get();

        $topCategories = \App\Models\OrderItem::join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, SUM(order_items.quantity) as total_sold')
            ->groupBy('categories.name')
            ->orderByDesc('total_sold')
            ->take(5)->get();

        return response()->json([
            'monthly_kpis'   => $months,
            'top_products'   => $topProducts,
            'top_categories' => $topCategories,
        ]);
    }

    public function alerts(): JsonResponse
    {
        return response()->json([
            'low_stock'           => Product::lowStock()->with('category:id,name')->take(10)->get(),
            'out_of_stock'        => Product::outOfStock()->take(10)->get(),
            'expiring_soon'       => Product::expiringSoon()->take(10)->get(),
            'pending_orders'      => Order::where('status', 'pending')->where('created_at', '<', now()->subHours(2))->count(),
            'pending_partners'    => \App\Models\Partner::where('status', 'pending')->count(),
            'pending_reviews'     => \App\Models\Review::where('is_approved', false)->count(),
            'pending_suggestions' => \App\Models\Suggestion::where('status', 'new')->count(),
        ]);
    }
}
