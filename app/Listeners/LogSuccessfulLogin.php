<?php

namespace App\Listeners;

use App\Models\AuthLog;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event)
    {
        // Careers-portal candidates (guard "applicant") are not HRMS users; this log is for employee/HR logins only.
        if ($event->guard === 'applicant') {
            return;
        }

        AuthLog::create([
            'auth_role' => $event->user->roles->pluck('name')->implode(', '), // if using spatie roles
            'auth_type' => 'login',
            'auth_id' => $event->user->id,
            'attempt_status' => 'success',
            'user_agent' => request()->userAgent(),
            'local_ip_address' => request()->ip(),
            'public_ip_address' => $this->getPublicIP(),
            'latitude' => request()->input('latitude'),
            'longitude' => request()->input('longitude'),
        ]);
    }

    protected function getPublicIP()
    {
        // Optional: use X-Forwarded-For if behind proxy
        return request()->header('X-Forwarded-For') ?? request()->ip();
    }
}
