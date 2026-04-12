<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Badge extends Model
{
    protected $fillable = ['name','name_en','description','image','icon','type','condition_key','condition_value','points_reward','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function users() { return $this->belongsToMany(User::class, 'user_badges')->withPivot('earned_at'); }
}
