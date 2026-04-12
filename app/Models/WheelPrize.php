<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WheelPrize extends Model
{
    protected $fillable = ['game_id','label','type','value','image','probability'];
    public function game() { return $this->belongsTo(Game::class); }
}
