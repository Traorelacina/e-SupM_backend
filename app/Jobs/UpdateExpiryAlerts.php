<?php
namespace App\Jobs;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateExpiryAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Today = red
        Product::whereDate('expiry_date', today())->update(['expiry_alert' => 'red']);
        // 2-3 days = orange
        Product::whereBetween('expiry_date', [today()->addDay(), today()->addDays(3)])->update(['expiry_alert' => 'orange']);
        // Soon = yellow (4-7 days)
        Product::whereBetween('expiry_date', [today()->addDays(4), today()->addDays(7)])->update(['expiry_alert' => 'yellow']);
        // Clear expired
        Product::where('expiry_date','<', today())->update(['is_active'=>false]);
    }
}
