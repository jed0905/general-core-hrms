<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TardinessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;
    public $tardinessCount;
    public $month;
    public $year;

    /**
     * Create a new message instance.
     */
    public function __construct($employee, $tardinessCount, $month, $year)
    {
        $this->employee = $employee->personalInformation->getFirstLastNameAttribute();
        $this->tardinessCount = $tardinessCount;
        $this->month = $month;
        $this->year = $year;
    }
    /**
     * Get the message envelope.
     */
    public function build()
    {
        return $this->subject('Tardiness Notice')
            ->view('emails.tardiness');
    }
}
