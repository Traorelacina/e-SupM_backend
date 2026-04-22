<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name','email','phone','password','avatar','role','status',
        'google_id','facebook_id','apple_id','provider',
        'loyalty_points','loyalty_level','total_points_earned',
        'language','notification_preferences','push_token',
        'date_of_birth','gender','ban_reason','banned_at',
    ];

    protected $hidden = ['password','remember_token','two_factor_secret','two_factor_recovery_codes'];

    protected $casts = [
        'email_verified_at'         => 'datetime',
        'two_factor_confirmed_at'   => 'datetime',
        'banned_at'                 => 'datetime',
        'notification_preferences'  => 'array',
        'date_of_birth'             => 'date',
        'loyalty_points'            => 'integer',
        'total_points_earned'       => 'integer',
    ];

    // ===== RELATIONSHIPS =====
    public function addresses()             { return $this->hasMany(Address::class); }
    public function defaultAddress()        { return $this->hasOne(Address::class)->where('is_default', true); }
    public function orders()                { return $this->hasMany(Order::class); }
    public function subscriptions()         { return $this->hasMany(Subscription::class); }
    public function cart()                  { return $this->hasOne(Cart::class); }
    public function wishlist()              { return $this->hasMany(Wishlist::class); }
    public function reviews()              { return $this->hasMany(Review::class); }
    public function loyaltyTransactions()   { return $this->hasMany(LoyaltyTransaction::class); }
    public function badges()               { return $this->belongsToMany(Badge::class, 'user_badges')->withPivot('earned_at'); }
    public function gameParticipations()    { return $this->hasMany(GameParticipant::class); }
    public function charityDonations()      { return $this->hasMany(CharityDonation::class); }
    public function partner()              { return $this->hasOne(Partner::class); }
    public function suggestions()          { return $this->hasMany(Suggestion::class); }

    // ===== HELPERS =====
    public function isAdmin(): bool        { return $this->role === 'admin'; }
    public function isClient(): bool       { return $this->role === 'client'; }
    public function isLivreur(): bool      { return $this->role === 'livreur'; }
    public function isPreparateur(): bool  { return $this->role === 'preparateur'; }
    public function isPartner(): bool      { return $this->role === 'partner'; }
    public function isBanned(): bool       { return $this->status === 'banned'; }

    public function addLoyaltyPoints(int $points, string $type, string $description = '', $orderId = null): LoyaltyTransaction
    {
        $multiplier = $this->getPointsMultiplier();
        $finalPoints = (int)($points * $multiplier);

        $this->increment('loyalty_points', $finalPoints);
        $this->increment('total_points_earned', $finalPoints);
        $this->updateLoyaltyLevel();

        return $this->loyaltyTransactions()->create([
            'points'      => $finalPoints,
            'type'        => $type,
            'description' => $description,
            'order_id'    => $orderId,
        ]);
    }

    public function spendLoyaltyPoints(int $points, string $description = ''): bool
    {
        if ($this->loyalty_points < $points) return false;
        $this->decrement('loyalty_points', $points);
        $this->loyaltyTransactions()->create([
            'points'      => -$points,
            'type'        => 'spent',
            'description' => $description,
        ]);
        return true;
    }

    public function selectiveSubscriptions()
    {
        return $this->hasMany(SelectiveSubscription::class);
    }

    private function updateLoyaltyLevel(): void
    {
        $level = match(true) {
            $this->total_points_earned >= 50000 => 'platinum',
            $this->total_points_earned >= 20000 => 'gold',
            $this->total_points_earned >= 5000  => 'silver',
            default                             => 'bronze',
        };
        $this->updateQuietly(['loyalty_level' => $level]);
    }

    public function getPointsMultiplier(): float
    {
        return match($this->loyalty_level) {
            'platinum' => 3.0,
            'gold'     => 2.0,
            'silver'   => 1.5,
            default    => 1.0,
        };
    }

    public function getTotalSpentThisMonthAttribute(): float
    {
        return $this->orders()
            ->where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');
    }
}
