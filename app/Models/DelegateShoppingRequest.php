<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DelegateShoppingRequest extends Model
{
    protected $table = 'delegate_shopping_requests';
    protected $fillable = ['user_id','list_text','list_image','list_audio','delivery_type','address_id','recipient_name','recipient_phone','status','estimated_amount','final_amount','partial_payment_made','notes'];
    protected $casts = ['estimated_amount'=>'float','final_amount'=>'float','partial_payment_made'=>'boolean'];
    public function user()    { return $this->belongsTo(User::class); }
    public function address() { return $this->belongsTo(Address::class); }
}
