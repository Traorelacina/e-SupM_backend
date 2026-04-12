<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Promotion extends Model
{
    protected $fillable = [
        'name','description','image','type','discount_type','discount_value',
        'buy_quantity','get_quantity','scope','category_id','product_id',
        'min_purchase','max_discount','is_active','is_flash','starts_at','ends_at',
    ];
    protected $casts = ['discount_value'=>'float','min_purchase'=>'float','max_discount'=>'float','is_active'=>'boolean','is_flash'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime'];

    public function category() { return $this->belongsTo(Category::class); }
    public function product()  { return $this->belongsTo(Product::class); }

    public function scopeActive($q)  { return $q->where('is_active', true)->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now())); }
    public function scopeFlash($q)   { return $q->where('is_flash', true)->active(); }
    public function isActive(): bool { return $this->is_active && (!$this->starts_at || $this->starts_at <= now()) && (!$this->ends_at || $this->ends_at >= now()); }
}
