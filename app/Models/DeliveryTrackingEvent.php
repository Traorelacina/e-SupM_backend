<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryTrackingEvent extends Model
{
    protected $fillable = ['delivery_id','status','message','latitude','longitude'];
    protected $casts = ['latitude'=>'float','longitude'=>'float'];
    public function delivery() { return $this->belongsTo(Delivery::class); }
}
