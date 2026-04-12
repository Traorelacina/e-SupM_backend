<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Game extends Model
{
    protected $fillable = [
        'name','description','image','video_url','type','status',
        'is_open_to_all','requires_registration','requires_purchase',
        'min_purchase_amount','purchase_type','starts_at','ends_at',
        'auto_activate_day','duration_days','participation_cooldown_days',
        'max_participants','has_countdown','time_limit_seconds','prizes','loyalty_points_prize',
    ];
    protected $casts = [
        'is_open_to_all'=>'boolean','requires_registration'=>'boolean','requires_purchase'=>'boolean',
        'has_countdown'=>'boolean','min_purchase_amount'=>'float','starts_at'=>'datetime','ends_at'=>'datetime',
    ];

    public function participants()    { return $this->hasMany(GameParticipant::class); }
    public function quizQuestions()   { return $this->hasMany(QuizQuestion::class)->orderBy('sort_order'); }
    public function wheelPrizes()     { return $this->hasMany(WheelPrize::class); }
    public function battleCandidates(){ return $this->hasMany(BattleCandidate::class); }
    public function winners()         { return $this->hasMany(GameParticipant::class)->where('is_winner', true); }

    public function scopeActive($q)    { return $q->where('status', 'active'); }
    public function isActive(): bool   { return $this->status === 'active'; }
    public function isEnded(): bool    { return $this->ends_at && $this->ends_at < now(); }
}
