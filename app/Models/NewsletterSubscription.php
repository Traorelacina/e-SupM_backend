<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NewsletterSubscription extends Model
{
    protected $table = 'newsletter_subscriptions';
    protected $fillable = ['email','user_id','is_active','token','confirmed_at'];
    protected $casts = ['is_active'=>'boolean','confirmed_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
