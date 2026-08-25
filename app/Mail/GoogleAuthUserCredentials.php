<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleAuthUserCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $username;
    public $password;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $username, $password)
    {
        $this->name = $name;
        $this->username = $username;
        $this->password = $password;
    }

    public function build()
    {
        return $this->subject('Your HRMS Account Credentials')
            ->view('emails.new_user_credentials', [
                'name' => $this->name,
                'username' => $this->username,
                'password' => $this->password,
            ]);
    }
}
