<?php
namespace App\Console\Commands;
use App\Jobs\AutoActivateGames;
use Illuminate\Console\Command;

class ActivateGames extends Command
{
    protected $signature   = 'esup:activate-games';
    protected $description = 'Auto activate/deactivate games based on schedule';
    public function handle(): void
    {
        AutoActivateGames::dispatch();
        $this->info('Games activation job dispatched.');
    }
}
