<?php

namespace App\Notifications;

use App\Models\LoginCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the one-time sign-in code. Queued so that answering a request
 * takes the same time whether or not the address has an account, and
 * encrypted because the queue payload carries the code in plain text.
 */
class LoginCodeNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

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
            ->subject(__('Your sign-in code: :code', ['code' => $this->code]))
            ->line(__('Enter this code to sign in:'))
            ->line("**{$this->code}**")
            ->line(__('The code expires in :minutes minutes and works once.', ['minutes' => LoginCode::MINUTES_VALID]))
            ->line(__('If you did not ask for a code, you can ignore this email.'));
    }
}
