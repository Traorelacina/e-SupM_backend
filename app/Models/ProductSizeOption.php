<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductSizeOption extends Model
{
    public $timestamps = false;
    protected $fillable = ['product_id','size','color','extra_price','stock'];
    public function product() { return $this->belongsTo(Product::class); }
}
