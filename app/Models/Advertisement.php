<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Advertisement extends Model
{
    protected $fillable = ['title','client_name','image','link','position','page','is_active','is_flashing','sort_order','views_count','clicks_count','starts_at','ends_at','slide_count'];
    protected $casts = ['is_active'=>'boolean','is_flashing'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime'];
    protected $appends = ['image_url'];
    public function scopeActive($q) { return $q->where('is_active', true)->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now())); }
    public function getImageUrlAttribute() { return asset('storage/' . $this->image); }
}
