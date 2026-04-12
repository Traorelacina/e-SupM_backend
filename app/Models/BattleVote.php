<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BattleVote extends Model
{
    protected $fillable = ['game_id','user_id','battle_candidate_id','battle_type'];
    public function game()      { return $this->belongsTo(Game::class); }
    public function user()      { return $this->belongsTo(User::class); }
    public function candidate() { return $this->belongsTo(BattleCandidate::class, 'battle_candidate_id'); }
}
