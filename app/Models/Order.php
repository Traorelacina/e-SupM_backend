<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'order_number','user_id','address_id','status','delivery_type','pickup_store','locker_id',
        'payment_method','payment_status','payment_reference','transaction_id',
        'subtotal','discount_amount','delivery_fee','loyalty_discount','total','coupon_code',
        'loyalty_points_earned','loyalty_points_used','notes','tracking_code',
        'subscription_id','is_subscription_order','is_priority',
        'paid_at','delivered_at','cancelled_at','cancel_reason',
    ];
    protected $casts = [
        'subtotal'=>'float','discount_amount'=>'float','delivery_fee'=>'float',
        'loyalty_discount'=>'float','total'=>'float',
        'paid_at'=>'datetime','delivered_at'=>'datetime','cancelled_at'=>'datetime',
        'is_subscription_order'=>'boolean','is_priority'=>'boolean',
    ];

    public function user()         { return $this->belongsTo(User::class); }
    public function address()      { return $this->belongsTo(Address::class); }
    public function items()        { return $this->hasMany(OrderItem::class); }
    public function delivery()     { return $this->hasOne(Delivery::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function coupon()       { return $this->belongsTo(Coupon::class, 'coupon_code', 'code'); }
    public function review()       { return $this->hasMany(Review::class); }

    public function scopePaid($q)       { return $q->where('payment_status', 'paid'); }
    public function scopeDelivered($q)  { return $q->where('status', 'delivered'); }
    public function scopePending($q)    { return $q->where('status', 'pending'); }

    public function isPaid(): bool       { return $this->payment_status === 'paid'; }
    public function isCancellable(): bool{ return in_array($this->status, ['pending','confirmed']); }
    public function isDelivered(): bool  { return $this->status === 'delivered'; }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= 'ESM-' . strtoupper(uniqid());
        });
    }
}
