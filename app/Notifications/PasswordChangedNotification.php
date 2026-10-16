<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your FacultyHUB password was changed')
            ->greeting('Password changed')
            ->line('Your FacultyHUB account password was recently changed.')
            ->line('If you did not make this change, contact your administrator immediately.')
            ->action('Open FacultyHUB', url('/login'));
    }
}
