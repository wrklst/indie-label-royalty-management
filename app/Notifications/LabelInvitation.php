<?php

namespace App\Notifications;

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone that a label gave them access. The link never expires:
 * it only leads to the page where they ask for a sign-in code.
 */
class LabelInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Label $label, public readonly LabelRole $role = LabelRole::Payee) {}

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
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':label gave you access to their royalty accounting', ['label' => $this->label->name]))
            ->greeting(__('Hello :name,', ['name' => self::plain($notifiable->name)]))
            ->line(__(':label gave you access to their royalty accounting.', ['label' => self::plain($this->label->name)]).' '.($this->role === LabelRole::Admin
                ? __('You can manage its catalog, transactions, contracts and statements there.')
                : __('You can read your statements and payouts there.')))
            ->action(__('Sign in'), route('login.code.create', ['email' => $notifiable->email]))
            ->line(__('You do not need a password: we email you a one-time code to sign in with.'));
    }

    /**
     * A name as plain words: the mail is written in Markdown, where a
     * label called "[Confirm your bank details](https://…)" would
     * become a link sent from this installation's address.
     */
    private static function plain(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[\[\]()<>*_`!#\\|]+/', ' ', $name)));
    }
}
