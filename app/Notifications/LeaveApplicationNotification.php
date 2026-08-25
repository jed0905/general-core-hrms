<?php

namespace App\Notifications;

use Carbon\Carbon;
use App\Models\Leave;
use Illuminate\Bus\Queueable;
use App\Models\LeaveApplication;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LeaveApplicationNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $leaveApplication;
    protected $employee_information;
    protected $immediate_supervisor;

    /**
     * Create a new notification instance.
     */
    public function __construct($leaveApplication, $employee_information, $immediate_supervisor)
    {
        $this->leaveApplication = $leaveApplication;
        $this->employee_information = $employee_information;
        $this->immediate_supervisor = $immediate_supervisor;
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

    // /**
    //  * Get the mail representation of the notification.
    //  */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->line('The introduction to the notification.')
    //         ->action('Notification Action', url('/'))
    //         ->line('Thank you for using our application!');
    // }

    public function toDatabase(object $notifiable)
    {
        $employee_name = $this->employee_information->getFullNameWithMiddleInitialAttribute();

        $from = Carbon::parse($this->leaveApplication->from)->format('M j, Y');
        $to = Carbon::parse($this->leaveApplication->to)->format('M j, Y');

        // ✅ Show single date if from and to are the same
        $date_range = ($from === $to) ? $from : "{$from} to {$to}";

        // Load leave application with its special leave relation
        $leaveApplication = LeaveApplication::with('specialLeaveCredit.specialLeave')
            ->find($this->leaveApplication->id);

        $leave = Leave::find($leaveApplication->leave_id);

        // Determine leave name
        if (
            strtolower($leave->name) === 'others' &&
            $leaveApplication->specialLeaveCredit?->specialLeave
        ) {
            $leave_name = $leaveApplication->specialLeaveCredit->specialLeave->name;
        } else {
            $leave_name = $leave->name;
        }

        $message = "{$employee_name} filed a {$leave_name} on {$date_range}.";

        return [
            'title' => 'Leave Application',
            'message' => $message,
        ];
    }


    public function toBroadcast($notifiable)
    {
        $employee_name = $this->employee_information->getFullNameWithMiddleInitialAttribute();

        $from = Carbon::parse($this->leaveApplication->from)->format('M j, Y');
        $to = Carbon::parse($this->leaveApplication->to)->format('M j, Y');

        // ✅ Show single date if from and to are the same
        $date_range = ($from === $to) ? $from : "{$from} to {$to}";

        // Load leave application with its special leave relation
        $leaveApplication = LeaveApplication::with('specialLeaveCredit.specialLeave')
            ->find($this->leaveApplication->id);

        $leave = Leave::find($leaveApplication->leave_id);

        // Determine leave name
        if (
            strtolower($leave->name) === 'others' &&
            $leaveApplication->specialLeaveCredit?->specialLeave
        ) {
            $leave_name = $leaveApplication->specialLeaveCredit->specialLeave->name;
        } else {
            $leave_name = $leave->name;
        }

        $message = "{$employee_name} filed a {$leave_name} on {$date_range}.";

        return [
            'title' => 'Leave Application',
            'message' => $message,
        ];
    }

    /**
     * Define the private channel for this broadcast
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('leave.status.' . $this->immediate_supervisor->id);
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $employee_name = $this->employee_information->getFullNameWithMiddleInitialAttribute();

        $from = Carbon::parse($this->leaveApplication->from)->format('M j, Y');
        $to = Carbon::parse($this->leaveApplication->to)->format('M j, Y');

        // ✅ Show single date if from and to are the same
        $date_range = ($from === $to) ? $from : "{$from} to {$to}";

        // Load leave application with its special leave relation
        $leaveApplication = LeaveApplication::with('specialLeaveCredit.specialLeave')
            ->find($this->leaveApplication->id);

        $leave = Leave::find($leaveApplication->leave_id);

        // Determine leave name
        if (
            strtolower($leave->name) === 'others' &&
            $leaveApplication->specialLeaveCredit?->specialLeave
        ) {
            $leave_name = $leaveApplication->specialLeaveCredit->specialLeave->name;
        } else {
            $leave_name = $leave->name;
        }

        $message = "{$employee_name} filed a {$leave_name} on {$date_range}.";

        return [
            'title' => 'Leave Application',
            'message' => $message,
        ];
    }
}
