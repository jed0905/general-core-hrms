<?php

namespace App\Notifications\Careers;

use App\Models\ApplicantAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The emailed link that proves the candidate owns the address. Only after
 * following it does the candidate choose a password and the account become
 * usable, so nobody can claim someone else's applicant record.
 */
class CompleteApplicantRegistration extends Notification
{
    public const VALID_HOURS = 24;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(ApplicantAccount $account): string
    {
        return URL::temporarySignedRoute('careers.register.complete', now()->addHours(self::VALID_HOURS), [
            'account' => $account->id,
            'hash' => sha1($account->email),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Complete your careers account')
            ->line('Thank you for registering. Follow the link below to confirm your email address and choose a password.')
            ->action('Complete registration', $this->url($notifiable))
            ->line('The link is valid for '.self::VALID_HOURS.' hours. If you did not register, you can ignore this email.');
    }
}
