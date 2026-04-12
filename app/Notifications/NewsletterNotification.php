<?php
namespace App\Notifications;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterNotification extends Notification
{
    public function __construct(public string $subject, public string $body) {}
    public function via($notifiable): array { return ['mail']; }
    public function toMail($notifiable): MailMessage { return (new MailMessage)->subject($this->subject)->line($this->body); }
    public function toArray($notifiable): array { return []; }
}
