<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model
{
    protected $fillable = [
        'order_id','product_id','product_name','product_sku','product_image',
        'unit_price','compare_price','quantity','total','size','color',
        'preparation_status','substitute_product_id',
    ];
    protected $casts = ['unit_price'=>'float','compare_price'=>'float','quantity'=>'integer','total'=>'float'];
    public function order()     { return $this->belongsTo(Order::class); }
    public function product()   { return $this->belongsTo(Product::class); }
    public function substitute(){ return $this->belongsTo(Product::class, 'substitute_product_id'); }
}
