<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CharityDonation extends Model
{
    protected $fillable = ['user_id','type','amount','product_id','quantity','payment_method','payment_reference','status','loyalty_points_earned','scratch_card_unlocked'];
    protected $casts = ['amount'=>'float','scratch_card_unlocked'=>'boolean'];
    public function user()    { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function vouchers(){ return $this->hasMany(CharityVoucher::class, 'donation_id'); }
}
