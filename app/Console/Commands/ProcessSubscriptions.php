<?php
namespace App\Console\Commands;
use App\Jobs\ProcessSubscriptionOrders;
use App\Jobs\SendSubscriptionReminders;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature   = 'esup:process-subscriptions';
    protected $description = 'Process all due subscription orders';

    public function handle(): void
    {
        $this->info('Processing subscription orders...');
        ProcessSubscriptionOrders::dispatch();
        SendSubscriptionReminders::dispatch();
        $this->info('Jobs dispatched successfully.');
    }
}
