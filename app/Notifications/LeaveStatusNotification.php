<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LeaveStatusNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $leaveApplication;
    protected $status;
    protected $remarks;

    /**
     * Create a new notification instance.
     */
    public function __construct($leaveApplication)
    {
        $this->leaveApplication = $leaveApplication;
        $this->status = $leaveApplication->status;
        $this->remarks = $leaveApplication->remarks;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->line('The introduction to the notification.')
    //         ->action('Notification Action', url('/'))
    //         ->line('Thank you for using our application!');
    // }

    public function toDatabase(object $notifiable)
    {
        return [
            'title' => 'Leave Application Update',
            'message' => "Your leave application for {$this->leaveApplication->leave->name} has been {$this->status}.",
            'status' => $this->status,
            'remarks' => $this->remarks,
            'leave_application_id' => $this->leaveApplication->id,
        ];
    }

    public function toBroadcast($notifiable)
    {
        $from = Carbon::parse($this->leaveApplication->from)->format('M j, Y');
        $to = Carbon::parse($this->leaveApplication->to)->format('M j, Y');

        // ✅ Show single date if from and to are the same
        $dateRange = ($from === $to) ? $from : "{$from} to {$to}";

        // ✅ Base message
        $message = "Your leave application for {$this->leaveApplication->leave->name} on {$dateRange} has been {$this->status}.";

        // ✅ Add remarks if disapproved or rejected
        if (in_array(strtolower($this->status), ['disapproved', 'rejected'])) {
            $remarks = trim($this->remarks ?? '');
            if ($remarks !== '') {
                $message .= " Remarks: {$remarks}";
            }
        }

        return new BroadcastMessage([
            'title' => 'Leave Application Update',
            'message' => $message,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'leave_application_id' => $this->leaveApplication->id,
        ]);
    }


    /**
     * Define the private channel for this broadcast
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('leave.status.' . $this->leaveApplication->employee_id);
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Leave Application Update',
            'message' => "Your leave application for {$this->leaveApplication->leave->name} has been {$this->status}.",
        ];
    }
}
