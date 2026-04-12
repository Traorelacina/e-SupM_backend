<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Delivery extends Model
{
    protected $fillable = ['order_id','driver_id','status','tracking_code','driver_latitude','driver_longitude','estimated_delivery_at','picked_up_at','delivered_at','delivery_notes','delivery_proof_image','recipient_name'];
    protected $casts = ['estimated_delivery_at'=>'datetime','picked_up_at'=>'datetime','delivered_at'=>'datetime','driver_latitude'=>'float','driver_longitude'=>'float'];
    public function order()  { return $this->belongsTo(Order::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function events() { return $this->hasMany(DeliveryTrackingEvent::class)->latest(); }
}
