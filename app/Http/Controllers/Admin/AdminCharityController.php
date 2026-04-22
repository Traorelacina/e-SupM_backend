<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CharityDonation;
use App\Models\CharityVoucher;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCharityController extends Controller
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    // ─── Tableau de bord ─────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $confirmedDonations = CharityDonation::where('status', 'confirmed');

        // Stats globales
        $stats = [
            'total_donated'       => CharityDonation::where('status', 'confirmed')->sum('amount'),
            'total_donated_fcfa'  => number_format(CharityDonation::where('status', 'confirmed')->sum('amount'), 0, ',', ' ') . ' FCFA',
            'donations_count'     => CharityDonation::where('status', 'confirmed')->count(),
            'pending_count'       => CharityDonation::where('status', 'pending')->count(),
            'distributed_count'   => CharityDonation::where('status', 'distributed')->count(),
            'vouchers_total'      => CharityVoucher::count(),
            'vouchers_used'       => CharityVoucher::where('is_used', true)->count(),
            'vouchers_pending'    => CharityVoucher::where('is_used', false)->count(),
            'products_gifted'     => CharityDonation::where('type', 'product')->where('status', 'confirmed')->sum('quantity'),
            'scratch_cards_unlocked' => CharityDonation::where('scratch_card_unlocked', true)->count(),
            'donors_count'        => CharityDonation::where('status', 'confirmed')->distinct('user_id')->count('user_id'),
        ];

        // Évolution mensuelle (12 derniers mois)
        $monthly = CharityDonation::selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(amount)                      as total,
                COUNT(*)                         as count
            ")
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Répartition par type
        $byType = CharityDonation::selectRaw('type, COUNT(*) as count, SUM(amount) as total')
            ->where('status', 'confirmed')
            ->groupBy('type')
            ->get();

        // Top donateurs
        $topDonors = CharityDonation::selectRaw('user_id, SUM(amount) as total, COUNT(*) as donations')
            ->where('status', 'confirmed')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('user:id,name,email')
            ->get();

        return response()->json([
            'success'  => true,
            'stats'    => $stats,
            'monthly'  => $monthly,
            'by_type'  => $byType,
            'top_donors' => $topDonors,
        ]);
    }

    // ─── Liste des dons ───────────────────────────────────────────

    public function donations(Request $request): JsonResponse
    {
        $q = CharityDonation::with([
            'user:id,name,email,phone',
            'product:id,name,price',
            'vouchers',
        ]);

        // Filtres
        if ($request->status) {
            $q->where('status', $request->status);
        }

        if ($request->type) {
            $q->where('type', $request->type);
        }

        if ($request->q) {
            $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$request->q}%")
                  ->orWhere('email', 'like', "%{$request->q}%")
            );
        }

        if ($request->date_from) {
            $q->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $q->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->scratch_unlocked === 'true') {
            $q->where('scratch_card_unlocked', true);
        }

        if ($request->min_amount) {
            $q->where('amount', '>=', $request->min_amount);
        }

        $donations = $q->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $donations,
        ]);
    }

    // ─── Détail d'un don ──────────────────────────────────────────

    public function showDonation(int $id): JsonResponse
    {
        $donation = CharityDonation::with([
            'user:id,name,email,phone',
            'product:id,name,price,primary_image',
            'vouchers',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $donation,
        ]);
    }

    // ─── Mise à jour du statut d'un don ───────────────────────────

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,confirmed,distributed,cancelled'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $donation = CharityDonation::with('user')->findOrFail($id);
        $oldStatus = $donation->status;

        DB::transaction(function () use ($donation, $request, $oldStatus) {
            $donation->update([
                'status'     => $request->status,
                'admin_note' => $request->note,
            ]);

            // Si on confirme un don voucher, déclencher les rewards si >= 5000
            if ($request->status === 'confirmed' && $oldStatus !== 'confirmed') {

                if ($donation->scratch_card_unlocked) {
                    // Notifier ou enregistrer que la carte à gratter est disponible
                    // (implémentation selon votre système de notification)
                }

                // S'assurer que les badges sont vérifiés
                if ($donation->user) {
                    $this->loyaltyService->checkAndAwardBadges($donation->user);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour : ' . $oldStatus . ' → ' . $request->status,
            'data'    => $donation->fresh(['user:id,name,email', 'vouchers']),
        ]);
    }

    // ─── Mise à jour en lot ───────────────────────────────────────

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'status' => ['required', 'in:pending,confirmed,distributed,cancelled'],
        ]);

        $updated = CharityDonation::whereIn('id', $request->ids)
            ->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} don(s) mis à jour",
        ]);
    }

    // ─── Déclencher manuellement la carte à gratter ───────────────

    public function triggerScratchCard(int $id): JsonResponse
    {
        $donation = CharityDonation::with('user')->findOrFail($id);

        if (!$donation->user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur introuvable'], 404);
        }

        $donation->update(['scratch_card_unlocked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Carte à gratter déclenchée pour ' . $donation->user->name,
        ]);
    }

    // ─── Liste des bons alimentaires ─────────────────────────────

    public function vouchers(Request $request): JsonResponse
    {
        $q = CharityVoucher::with(['donation.user:id,name,email']);

        if ($request->q) {
            $q->where('code', 'like', "%{$request->q}%");
        }

        if ($request->is_used === 'true') {
            $q->where('is_used', true);
        } elseif ($request->is_used === 'false') {
            $q->where('is_used', false);
        }

        if ($request->expired === 'true') {
            $q->where('expires_at', '<', now());
        }

        if ($request->min_amount) {
            $q->where('amount', '>=', $request->min_amount);
        }

        $vouchers = $q->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $vouchers,
        ]);
    }

    // ─── Marquer un bon comme utilisé ────────────────────────────

    public function useVoucher(Request $request, string $code): JsonResponse
    {
        $voucher = CharityVoucher::where('code', strtoupper($code))
            ->with(['donation.user'])
            ->first();

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Bon introuvable'], 404);
        }

        if ($voucher->is_used) {
            return response()->json(['success' => false, 'message' => 'Ce bon a déjà été utilisé'], 422);
        }

        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            return response()->json(['success' => false, 'message' => 'Ce bon est expiré'], 422);
        }

        $voucher->update([
            'is_used'      => true,
            'used_at'      => now(),
            'used_note'    => $request->note,
        ]);

        // Passer le don en "distribué"
        if ($voucher->donation) {
            $voucher->donation->update(['status' => 'distributed']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bon validé et marqué comme utilisé',
            'data'    => $voucher->fresh(['donation.user']),
        ]);
    }

    // ─── Créer un bon manuellement ────────────────────────────────

    public function createVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'amount'     => ['required', 'numeric', 'min:500'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'note'       => ['nullable', 'string', 'max:255'],
        ]);

        $voucher = CharityVoucher::create([
            'code'       => 'CHR-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'amount'     => $request->amount,
            'expires_at' => $request->expires_at ?? now()->addYear(),
            'note'       => $request->note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bon créé avec succès',
            'data'    => $voucher,
        ], 201);
    }

    // ─── Exporter les dons (CSV simple) ──────────────────────────

    public function exportDonations(Request $request)
    {
        $donations = CharityDonation::with(['user:id,name,email', 'product:id,name'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type,   fn($q) => $q->where('type',   $request->type))
            ->latest()
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="dons_charity_' . now()->format('Ymd') . '.csv"',
        ];

        $callback = function () use ($donations) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($handle, ['ID', 'Donateur', 'Email', 'Type', 'Montant (FCFA)', 'Produit', 'Qté', 'Statut', 'Carte à gratter', 'Points fidélité', 'Date']);
            foreach ($donations as $d) {
                fputcsv($handle, [
                    $d->id,
                    $d->user?->name ?? '—',
                    $d->user?->email ?? '—',
                    $d->type === 'voucher' ? 'Bon alimentaire' : 'Produit',
                    $d->amount,
                    $d->product?->name ?? '—',
                    $d->quantity ?? '—',
                    $d->status,
                    $d->scratch_card_unlocked ? 'Oui' : 'Non',
                    $d->loyalty_points_earned ?? 0,
                    $d->created_at->format('d/m/Y H:i'),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}