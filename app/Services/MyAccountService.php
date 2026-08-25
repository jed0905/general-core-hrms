<?php

namespace App\Services;

use App\Models\User;
use App\Mail\OtpMail;
use App\Models\Employee;
use App\Models\UserOtpCode;
use Illuminate\Support\Facades\DB;
use App\Models\PersonalInformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class MyAccountService
{
    public function employeeInformation()
    {
        $employee_id = auth()->user()->employee_id;
        $employee = Employee::with('personalInformation')->where('id', $employee_id)->first();
        return $employee;
    }

    public function changeUsername(array $data)
    {
        $employee_id = auth()->user()->employee_id;
        $user = User::where('employee_id', $employee_id)->first();
        $user->update([
            'username' => $data['username'],
        ]);
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete(); // Logs the user out everywhere
        return $user;
    }

    public function changePassword()
    {
        $employee_id = auth()->user()->employee_id;
        $employee = Employee::with('personalInformation')
            ->where('id', $employee_id)->first();

        // dd($employee);

        $recentRequests = UserOtpCode::where('employee_number', $employee->employee_number)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->where('action', 'change_password')
            ->count();

        if ($recentRequests >= 3) {
            return redirect()->back()->withErrors(['errors' => 'Too many OTP requests. Try again later.']);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Save hashed OTP
        UserOtpCode::create([
            'employee_number' => $employee->employee_number,
            'otp' => Hash::make($otp),
            'action' => 'change_password',
            'expires_at' => now()->addMinutes(5), // valid for 5 minutes
        ]);

        $subject = "Change Password Request";
        $message = "We received a request to change your password for the Don Mariano Marcos Memorial State University (DHRMS) portal. Please use the one-time pin (OTP) provided below to proceed with resetting your password.";

        // Send OTP to email
        Mail::to($employee->personalInformation->email)->queue(new OtpMail($otp, $subject, $message));
    }

    public function updatePassword($data, $otp)
    {
        $user = Auth::user();
        $employee = Employee::findOrFail($user->employee_id);

        $otpRecord = UserOtpCode::where('employee_number', $employee->employee_number)
            ->where('action', 'change_password')
            ->latest()
            ->first();

        $isOtpValid = $otpRecord && Hash::check($otp, $otpRecord->otp);

        if (!$isOtpValid) {
            throw new \Exception("Invalid OTP.");
        }

        // Check current password if correct
        if (!Hash::check($data['currentPassword'], $user->password)) {
            throw new \Exception("Your current password is incorrect.");
        }

        DB::beginTransaction();
        try {
            // Update user password
            $user->update([
                'password' => Hash::make($data['newPassword'])
            ]);

            // Update OTP record as used
            $otpRecord->update([
                'used' => 1,
            ]);

            // Delete all sessions
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e; // rethrow so Laravel can handle it
        }

    }

    public function sendActivateOtp()
    {
        $user = Auth::user();
        // dd($user);

        $employee = Employee::with('personalInformation')
            ->where('id', $user->employee_id)->first();

        $email = $employee->personalInformation->email;

        $recentRequests = UserOtpCode::where('employee_number', $employee->employee_number)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->where('action', 'enable_two_factor')
            ->count();

        if ($recentRequests >= 3) {
            return redirect()->back()->withErrors(['errors' => 'Too many OTP requests. Try again later.']);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Save hashed OTP
        UserOtpCode::create([
            'employee_number' => $employee->employee_number,
            'otp' => Hash::make($otp),
            'action' => 'enable_two_factor',
            'expires_at' => now()->addMinutes(5), // valid for 5 minutes
        ]);


        $subject = "Two Factor Authentication";
        $message = "Use the One-Time Password (OTP) below to activate Two-Factor Authentication for your DHRMS account. Do not share this code with anyone.";

        // Send OTP to email
        Mail::to($email)->queue(new OtpMail($otp, $subject, $message));

    }


    public function activateTwoFactorAuthentication($otp)
    {
        $user = Auth::user();
        $employee = Employee::findOrFail($user->employee_id);

        $otpRecord = UserOtpCode::where('employee_number', $employee->employee_number)
            ->where('action', 'enable_two_factor')
            ->latest()
            ->first();

        $isOtpValid = $otpRecord && Hash::check($otp, $otpRecord->otp);

        if (!$isOtpValid) {
            throw new \Exception("Invalid OTP.");
        }

        DB::beginTransaction();
        try {
            if ($user->is_two_factor_enabled === 1) {
                $user->update([
                    'is_two_factor_enabled' => 0,
                ]);
            } else {
                $user->update([
                    'is_two_factor_enabled' => 1,
                ]);
            }


            $otpRecord->update([
                'used' => 1
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e; // rethrow so Laravel can handle it
        }
    }

}
