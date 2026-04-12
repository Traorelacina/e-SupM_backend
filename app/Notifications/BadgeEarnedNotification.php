<?php
namespace App\Notifications;
use App\Models\Badge;
use Illuminate\Notifications\Notification;
class BadgeEarnedNotification extends Notification
{
    public function __construct(public Badge $badge) {}
    public function via($notifiable): array { return ['database']; }
    public function toArray($notifiable): array { return ['type'=>'badge_earned','badge_id'=>$this->badge->id,'badge_name'=>$this->badge->name,'badge_image'=>$this->badge->image]; }
}
