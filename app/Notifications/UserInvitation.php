<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $appName = config('app.name');

        return (new MailMessage)
            ->subject(__('You have been invited to :app', ['app' => $appName]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->name]))
            ->line(__('An account was created for you at :app. Use the button below to choose your password and sign in for the first time.', ['app' => $appName]))
            ->action(__('Set my password'), $url)
            ->line(__('This invitation link expires in :count minutes.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(__('If you were not expecting this invitation, you can safely ignore this email.'));
    }
}
