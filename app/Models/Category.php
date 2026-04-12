<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;
    protected $fillable = ['parent_id','name','name_en','slug','description','image','icon','color','sort_order','is_active','show_in_menu','is_premium'];
    protected $casts = ['is_active'=>'boolean','show_in_menu'=>'boolean','is_premium'=>'boolean'];

    public function parent()        { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children()      { return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order'); }
    public function products()      { return $this->hasMany(Product::class); }
    public function activeProducts(){ return $this->hasMany(Product::class)->where('is_active', true); }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeRoot($q)    { return $q->whereNull('parent_id'); }
    public function scopeInMenu($q)  { return $q->where('show_in_menu', true); }
}
