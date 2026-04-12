<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionItem extends Model
{
    protected $fillable = ['subscription_id','product_id','quantity','price'];
    protected $casts = ['price'=>'float','quantity'=>'integer'];
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function product()      { return $this->belongsTo(Product::class); }
}
