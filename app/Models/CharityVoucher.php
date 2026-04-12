<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CharityVoucher extends Model
{
    protected $fillable = ['code','donation_id','amount','is_used','used_by','used_at','expires_at'];
    protected $casts = ['is_used'=>'boolean','amount'=>'float','used_at'=>'datetime','expires_at'=>'datetime'];
    public function donation() { return $this->belongsTo(CharityDonation::class); }
    public function usedBy()   { return $this->belongsTo(User::class, 'used_by'); }
}
