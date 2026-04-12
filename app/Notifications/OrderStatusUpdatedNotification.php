<?php
namespace App\Notifications;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['mail','database']; }
    public function toMail($notifiable): MailMessage
    {
        $statusLabel = match($this->order->status) {
            'preparing'  => 'En préparation',
            'ready'      => 'Prête pour livraison',
            'dispatched' => 'En cours de livraison',
            'delivered'  => 'Livrée',
            'cancelled'  => 'Annulée',
            default      => ucfirst($this->order->status),
        };
        return (new MailMessage)
            ->subject("e-Sup'M - Commande #{$this->order->order_number} : {$statusLabel}")
            ->line("Le statut de votre commande a été mis à jour : **{$statusLabel}**")
            ->action('Voir ma commande', config('app.frontend_url') . "/orders/{$this->order->id}");
    }
    public function toArray($notifiable): array { return ['type'=>'order_status','order_id'=>$this->order->id,'status'=>$this->order->status]; }
}
