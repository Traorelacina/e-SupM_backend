<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id','name','type','preset_type','frequency','delivery_day','delivery_week_of_month',
        'delivery_type','address_id','payment_method','payment_token',
        'subtotal','discount_percent','total','status',
        'next_delivery_at','suspended_until','cancelled_at','cancel_reason','total_orders_generated',
    ];
    protected $casts = [
        'subtotal'=>'float','discount_percent'=>'float','total'=>'float',
        'next_delivery_at'=>'datetime','suspended_until'=>'datetime','cancelled_at'=>'datetime',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function address() { return $this->belongsTo(Address::class); }
    public function items()   { return $this->hasMany(SubscriptionItem::class); }
    public function orders()  { return $this->hasMany(Order::class); }

    public function isActive(): bool     { return $this->status === 'active'; }
    public function isSuspended(): bool  { return $this->status === 'suspended'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }

    public function calculateTotal(): float {
        $sub = $this->items->sum(fn($i) => $i->price * $i->quantity);
        return round($sub * (1 - $this->discount_percent / 100), 2);
    }

    public function computeNextDelivery(): \Carbon\Carbon {
        $now = now();
        return match($this->frequency) {
            'weekly'     => $now->addWeek(),
            'biweekly'   => $now->addWeeks(2),
            default      => $now->addMonth(),
        };
    }
}
