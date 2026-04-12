<?php
namespace App\Jobs;
use App\Models\Subscription;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OrderService $orderService): void
    {
        $subscriptions = Subscription::where('status','active')
            ->where('next_delivery_at','<=', now())
            ->with(['items.product','user','address'])
            ->get();

        Log::info("Processing {$subscriptions->count()} subscription orders");

        foreach ($subscriptions as $subscription) {
            try {
                $order = $orderService->createSubscriptionOrder($subscription);
                Log::info("Subscription order created: #{$order->order_number} for user {$subscription->user_id}");
            } catch (\Exception $e) {
                Log::error("Failed to create subscription order for sub #{$subscription->id}: " . $e->getMessage());
                // Notify admin
            }
        }
    }
}
