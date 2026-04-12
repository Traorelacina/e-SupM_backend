<?php
namespace App\Notifications;
use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerRejectedNotification extends Notification
{
    public function __construct(public Partner $partner, public string $reason) {}
    public function via($notifiable): array { return ['mail']; }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject("e-Sup'M - Candidature partenaire")->line("Votre candidature n'a pas pu être acceptée.")->line("Motif : {$this->reason}");
    }
    public function toArray($notifiable): array { return ['type'=>'partner_rejected']; }
}
