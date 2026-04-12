<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Recipe extends Model
{
    protected $fillable = ['author_id','title','slug','description','image','ingredients','steps','prep_time_minutes','cook_time_minutes','servings','difficulty','category','is_published','views_count'];
    protected $casts = ['ingredients'=>'array','steps'=>'array','is_published'=>'boolean'];
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
