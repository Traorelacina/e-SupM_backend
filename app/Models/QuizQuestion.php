<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuizQuestion extends Model
{
    protected $fillable = ['game_id','question','options','correct_answer','theme','points','time_limit_seconds','sort_order'];
    protected $casts = ['options'=>'array'];
    protected $hidden = ['correct_answer'];
    public function game() { return $this->belongsTo(Game::class); }
}
