<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Coupon extends Model
{
    protected $fillable = ['code','description','discount_type','discount_value','min_purchase','max_discount','max_uses','max_uses_per_user','used_count','is_active','is_first_order_only','starts_at','expires_at'];
    protected $casts = ['discount_value'=>'float','min_purchase'=>'float','max_discount'=>'float','is_active'=>'boolean','is_first_order_only'=>'boolean','starts_at'=>'datetime','expires_at'=>'datetime'];

    public function usages() { return $this->hasMany(CouponUsage::class); }

    public function isValid(): bool {
        return $this->is_active
            && (!$this->max_uses || $this->used_count < $this->max_uses)
            && (!$this->starts_at || $this->starts_at <= now())
            && (!$this->expires_at || $this->expires_at >= now());
    }

    public function calculateDiscount(float $subtotal): float {
        if (!$this->min_purchase || $subtotal < $this->min_purchase) return 0;
        $discount = $this->discount_type === 'percentage'
            ? $subtotal * ($this->discount_value / 100)
            : $this->discount_value;
        return $this->max_discount ? min($discount, $this->max_discount) : $discount;
    }
}
