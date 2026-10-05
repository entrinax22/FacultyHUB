<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class StudentActivityNotification extends Notification
{
    public function __construct(
        private readonly array $data
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }
}
