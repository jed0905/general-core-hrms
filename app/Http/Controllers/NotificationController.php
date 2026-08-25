<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getNotifications()
    {
        return auth()->user()->employee->notifications;
    }

    public function markAsRead(Request $request)
    {
        $employee = auth()->user()->employee;

        // Mark all unread notifications as read
        $employee->unreadNotifications->markAsRead();
    }
}
