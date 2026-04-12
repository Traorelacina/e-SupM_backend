<?php
namespace App\Console;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Process subscriptions every day at 6am
        $schedule->command('esup:process-subscriptions')->dailyAt('06:00');
        // Activate/close games every day at midnight
        $schedule->command('esup:activate-games')->dailyAt('00:01');
        // Update expiry alerts daily
        $schedule->job(new \App\Jobs\UpdateExpiryAlerts)->daily();
        // Send subscription reminders
        $schedule->job(new \App\Jobs\SendSubscriptionReminders)->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
