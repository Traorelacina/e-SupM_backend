<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Partner extends Model
{
    protected $fillable = ['user_id','company_name','contact_name','email','phone','address','logo','description','proof_images','type','status','rejection_reason','approved_at','show_on_homepage','sort_order'];
    protected $casts = ['proof_images'=>'array','approved_at'=>'datetime','show_on_homepage'=>'boolean'];
    public function user()     { return $this->belongsTo(User::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function isApproved(): bool { return $this->status === 'approved'; }
}
