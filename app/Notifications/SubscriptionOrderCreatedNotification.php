<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionOrderCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['mail','database']; }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("e-Sup'M - Votre panier mensuel est en préparation !")
            ->line("Votre panier essentiel #{$this->order->order_number} est en cours de préparation.")
            ->line("Total : {$this->order->total} FCFA (remise abonné incluse)")
            ->action('Voir mon abonnement', config('app.frontend_url') . '/subscriptions');
    }
    public function toArray($notifiable): array { return ['type'=>'subscription_order','order_id'=>$this->order->id]; }
}
