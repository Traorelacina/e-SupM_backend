<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['mail','database']; }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("e-Sup'M - Commande #{$this->order->order_number} confirmée !")
            ->greeting("Bonjour {$notifiable->name} !")
            ->line("Votre commande #{$this->order->order_number} a été confirmée et payée avec succès.")
            ->line("Montant total : {$this->order->total} FCFA")
            ->line("Points fidélité gagnés : {$this->order->loyalty_points_earned} points")
            ->action('Suivre ma commande', config('app.frontend_url') . "/orders/{$this->order->id}")
            ->line("Merci pour votre confiance !");
    }
    public function toArray($notifiable): array { return ['type'=>'order_confirmed','order_id'=>$this->order->id,'order_number'=>$this->order->order_number,'total'=>$this->order->total]; }
}
