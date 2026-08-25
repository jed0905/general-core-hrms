<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PermissionHelper
{
    public static function getPermissions()
    {
        $modules = [
            [
                'to' => 'maintenance.dashboard',
                'icon' => 'mdi-tools',
                'label' => 'Maintenance',
                'disabled' => false,
            ],
            [
                'to' => 'employee-maintenance.dashboard',
                'icon' => 'mdi-tools',
                'label' => 'Employee Management',
                'disabled' => false,
            ],
        ];

        // Debug all guards
        $guards = array_keys(config('auth.guards'));
        Log::info('Available guards:', ['guards' => $guards]);

        // Debug current guard
        $currentGuard = Auth::getDefaultDriver();
        Log::info('Current default guard:', ['guard' => $currentGuard]);

        // Check authentication
        $isAuthenticated = Auth::check();
        Log::info('Authentication check:', ['authenticated' => $isAuthenticated]);

        if ($isAuthenticated) {
            $user = Auth::user();

            if (!$user) {
                Log::error('User is null after authentication check');
                return [];
            }

            if (!isset($user->role_id)) {
                Log::error('User role is not set', ['user' => get_object_vars($user)]);
                return [];
            }

            if($user->username == 'superadmin') {
                return [
                    $modules[0],
                    $modules[1],
                ];
            }

            // switch ($user->role_id) {
            //     // Super Admin
            //     case '0':
            //         return [
            //             $modules[0],
            //             $modules[1],
            //         ];
            //         break;

            //     default:
            //         Log::info('No matching role found', ['role' => $user->role_id]);
            //         return [];
            //         break;
            // }
        }

        Log::error('User is not authenticated');
        return [];
    }
}
