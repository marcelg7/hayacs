<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class WelcomeNewUser extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

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
        $setupUrl = URL::temporarySignedRoute(
            'password.setup',
            now()->addDays(7),
            ['user' => $notifiable->id]
        );

        return (new MailMessage)
            ->subject('Welcome to Hay ACS - Set Up Your Password')
            ->greeting("Hello {$notifiable->name}!")
            ->line('An account has been created for you on the Hay ACS (Auto Configuration Server).')
            ->line("Your role: **" . ucfirst($notifiable->role) . "**")
            ->line('To get started, please set up your secure password by clicking the button below.')
            ->action('Set Up Password', $setupUrl)
            ->line('This link will expire in 7 days.')
            ->line('**Password Requirements:**')
            ->line('- At least 12 characters')
            ->line('- Upper and lowercase letters')
            ->line('- At least one number')
            ->line('- At least one symbol')
            ->line('- Must not be a previously compromised password')
            ->salutation('Welcome to the team!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
