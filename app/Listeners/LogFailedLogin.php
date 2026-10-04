<?php

namespace App\Listeners;

use App\Models\AuthLog;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event)
    {
        // Careers-portal candidates (guard "applicant") are not HRMS users; this log is for employee/HR logins only.
        if ($event->guard === 'applicant') {
            return;
        }

        $user = $event->user;
        $roleNames = null;

        if ($user && method_exists($user, 'roles')) {
            $roleNames = $user->roles->pluck('name')->implode(', ');
        }

        AuthLog::create([
            'auth_role' => $roleNames,
            'auth_type' => 'login',
            'auth_id' => $user->id ?? $event->credentials['username'] ?? $event->credentials['email'] ?? 'unknown',
            'attempt_status' => 'failed',
            'user_agent' => request()->userAgent(),
            'local_ip_address' => request()->ip(),
            'public_ip_address' => $this->getPublicIP(),
            'latitude' => request()->input('latitude'),
            'longitude' => request()->input('longitude'),
        ]);
    }

    protected function getPublicIP()
    {
        return request()->header('X-Forwarded-For') ?? request()->ip();
    }
}
