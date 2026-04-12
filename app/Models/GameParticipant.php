<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GameParticipant extends Model
{
    protected $fillable = ['game_id','user_id','answer','score','is_winner','prize','prize_claimed','loyalty_points_won','metadata','participated_at'];
    protected $casts = ['answer'=>'array','metadata'=>'array','is_winner'=>'boolean','prize_claimed'=>'boolean','participated_at'=>'datetime'];
    public function game() { return $this->belongsTo(Game::class); }
    public function user() { return $this->belongsTo(User::class); }
}
