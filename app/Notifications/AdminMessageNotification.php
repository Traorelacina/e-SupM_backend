<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public string $title, public string $body) {}
    public function via($notifiable): array { return ['mail','database']; }
    public function toMail($notifiable): MailMessage { return (new MailMessage)->subject($this->title)->line($this->body); }
    public function toArray($notifiable): array { return ['type'=>'admin_message','title'=>$this->title,'body'=>$this->body]; }
}
