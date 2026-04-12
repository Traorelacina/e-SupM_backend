<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\LoyaltyTransaction;
use App\Models\User;

class LoyaltyService
{
    /**
     * Award points to a user
     */
    public function awardPoints(User $user, int $points, string $type, string $description = '', ?int $orderId = null, ?int $gameId = null): LoyaltyTransaction
    {
        $finalPoints = (int)($points * $user->getPointsMultiplier());

        $user->increment('loyalty_points', $finalPoints);
        $user->increment('total_points_earned', $finalPoints);

        $this->updateLevel($user);

        return $user->loyaltyTransactions()->create([
            'points'      => $finalPoints,
            'type'        => $type,
            'description' => $description,
            'order_id'    => $orderId,
            'game_id'     => $gameId,
        ]);
    }

    /**
     * Check and award badges based on user activity
     */
    public function checkAndAwardBadges(User $user): array
    {
        $user->refresh();
        $awardedBadges = [];
        $activeBadges = Badge::where('is_active', true)->get();

        foreach ($activeBadges as $badge) {
            if ($user->badges()->where('badge_id', $badge->id)->exists()) continue;

            $qualified = match($badge->condition_key) {
                'orders_count'           => $user->orders()->where('payment_status', 'paid')->count() >= $badge->condition_value,
                'total_spent'            => $user->orders()->where('payment_status', 'paid')->sum('total') >= $badge->condition_value,
                'loyalty_points_total'   => $user->total_points_earned >= $badge->condition_value,
                'reviews_count'          => $user->reviews()->where('is_approved', true)->count() >= $badge->condition_value,
                'subscriptions_count'    => $user->subscriptions()->where('status', 'active')->count() >= $badge->condition_value,
                'charity_donations'      => $user->charityDonations()->where('status', 'confirmed')->count() >= $badge->condition_value,
                'charity_amount'         => $user->charityDonations()->where('status', 'confirmed')->sum('amount') >= $badge->condition_value,
                'games_won'              => $user->gameParticipations()->where('is_winner', true)->count() >= $badge->condition_value,
                'consecutive_months'     => $this->hasOrderedConsecutiveMonths($user, $badge->condition_value),
                default                  => false,
            };

            if ($qualified) {
                $user->badges()->attach($badge->id, ['earned_at' => now()]);
                if ($badge->points_reward > 0) {
                    $this->awardPoints($user, $badge->points_reward, 'bonus', "Badge obtenu: {$badge->name}");
                }
                $awardedBadges[] = $badge;
                $user->notify(new \App\Notifications\BadgeEarnedNotification($badge));
            }
        }

        return $awardedBadges;
    }

    /**
     * Calculate points needed for next level
     */
    public function getLevelProgress(User $user): array
    {
        $levels = [
            'bronze'   => ['min' => 0,     'max' => 5000,  'next' => 'silver'],
            'silver'   => ['min' => 5000,  'max' => 20000, 'next' => 'gold'],
            'gold'     => ['min' => 20000, 'max' => 50000, 'next' => 'platinum'],
            'platinum' => ['min' => 50000, 'max' => null,  'next' => null],
        ];

        $current = $levels[$user->loyalty_level];
        $earned = $user->total_points_earned;

        if (!$current['max']) {
            return ['level' => 'platinum', 'progress' => 100, 'points_to_next' => 0, 'next_level' => null];
        }

        $progress = min(100, (($earned - $current['min']) / ($current['max'] - $current['min'])) * 100);
        $pointsToNext = max(0, $current['max'] - $earned);

        return [
            'level'          => $user->loyalty_level,
            'next_level'     => $current['next'],
            'progress'       => round($progress, 1),
            'points_to_next' => $pointsToNext,
            'current_points' => $user->loyalty_points,
            'total_earned'   => $user->total_points_earned,
            'multiplier'     => $user->getPointsMultiplier(),
        ];
    }

    /**
     * Check if scratch card should be unlocked for user this month
     */
    public function checkScratchCardEligibility(User $user): bool
    {
        $monthlySpend = $user->orders()
            ->where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');

        if ($monthlySpend < 15000) return false;

        // Check if already used this month
        $alreadyUsed = $user->gameParticipations()
            ->whereHas('game', fn($q) => $q->where('type', 'carte_gratter'))
            ->whereMonth('created_at', now()->month)
            ->exists();

        return !$alreadyUsed;
    }

    /**
     * Check wheel eligibility (50k wholesale or 15k regular)
     */
    public function checkWheelEligibility(User $user, int $wheelNumber): bool
    {
        $monthlySpend = $user->orders()
            ->where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->sum('total');

        $threshold = $wheelNumber === 1 ? 50000 : 15000;
        return $monthlySpend >= $threshold;
    }

    private function updateLevel(User $user): void
    {
        $earned = $user->fresh()->total_points_earned;
        $level = match(true) {
            $earned >= 50000 => 'platinum',
            $earned >= 20000 => 'gold',
            $earned >= 5000  => 'silver',
            default          => 'bronze',
        };
        User::where('id', $user->id)->update(['loyalty_level' => $level]);
    }

    private function hasOrderedConsecutiveMonths(User $user, int $months): bool
    {
        for ($i = 0; $i < $months; $i++) {
            $date = now()->subMonths($i);
            $ordered = $user->orders()
                ->where('payment_status', 'paid')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->exists();
            if (!$ordered) return false;
        }
        return true;
    }
}
