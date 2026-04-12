<?php
namespace App\Jobs;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSubscriptionReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Send reminder 24h before next delivery
        $subscriptions = Subscription::where('status','active')
            ->whereBetween('next_delivery_at',[now()->addHours(23), now()->addHours(25)])
            ->with('user')
            ->get();

        foreach ($subscriptions as $sub) {
            $sub->user->notify(new \App\Notifications\AdminMessageNotification(
                "Votre panier du mois arrive demain !",
                "Votre panier essentiel sera expédié demain. Souhaitez-vous le modifier ? Connectez-vous avant minuit."
            ));
        }
    }
}
