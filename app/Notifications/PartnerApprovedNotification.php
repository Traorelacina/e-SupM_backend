<?php
namespace App\Notifications;
use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerApprovedNotification extends Notification
{
    public function __construct(public Partner $partner) {}
    public function via($notifiable): array { return ['mail']; }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject("Bienvenue sur e-Sup'M Partenaires !")->line("Votre candidature en tant que partenaire a été approuvée. Bienvenue !");
    }
    public function toArray($notifiable): array { return ['type'=>'partner_approved']; }
}
