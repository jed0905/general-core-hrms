<?php

namespace App\Notifications\Careers;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Careers-portal password reset (Laravel password broker "applicant_accounts").
 */
class ResetApplicantPassword extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('careers.password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Reset your careers account password')
            ->line('We received a request to reset the password of your careers account.')
            ->action('Reset password', $url)
            ->line('The link is valid for 60 minutes. If you did not ask for this, you can ignore this email.');
    }
}
