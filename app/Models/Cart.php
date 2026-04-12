<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Cart extends Model
{
    protected $fillable = ['user_id','session_id','coupon_code','coupon_discount','expires_at'];
    protected $casts = ['coupon_discount'=>'float','expires_at'=>'datetime'];

    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(CartItem::class); }

    public function getSubtotalAttribute(): float {
        return $this->items->sum(fn($i) => $i->price * $i->quantity);
    }
    public function getTotalAttribute(): float {
        return max(0, $this->subtotal - $this->coupon_discount);
    }
    public function getTotalItemsAttribute(): int {
        return $this->items->sum('quantity');
    }
}
