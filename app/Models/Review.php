<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Review extends Model
{
    protected $fillable = ['user_id','product_id','order_id','rating','comment','images','is_approved','helpful_count','loyalty_points_earned'];
    protected $casts = ['images'=>'array','is_approved'=>'boolean'];
    public function user()    { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function order()   { return $this->belongsTo(Order::class); }
}
