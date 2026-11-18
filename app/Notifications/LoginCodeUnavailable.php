<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone who asked for a sign-in code why none was sent: an email
 * code would bypass their two-factor authentication.
 */
class LoginCodeUnavailable extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Sign-in codes are disabled for your account'))
            ->line(__('Someone asked for a sign-in code for your account. No code was sent, because your account is protected by two-factor authentication.'))
            ->action(__('Log in with your password'), route('login'))
            ->line(__('If this was not you, you can ignore this email.'));
    }
}
