<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BattleCandidate extends Model
{
    protected $fillable = ['game_id','product_id','name','image','battle_type','votes_count'];
    public function game()    { return $this->belongsTo(Game::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
