<?php
namespace App\Jobs;
use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoActivateGames implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $dayNames = ['0'=>'dimanche','1'=>'lundi','2'=>'mardi','3'=>'mercredi','4'=>'jeudi','5'=>'vendredi','6'=>'samedi'];
        $today = $dayNames[now()->dayOfWeek];

        // Activate games scheduled for today
        Game::where('auto_activate_day', $today)->where('status','draft')
            ->each(function (Game $game) {
                $game->update(['status'=>'active','starts_at'=>now(),'ends_at'=>now()->addDays($game->duration_days ?? 14)]);
            });

        // Close expired games
        Game::where('status','active')->where('ends_at','<', now())
            ->update(['status'=>'closed']);
    }
}
