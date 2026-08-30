<?php

namespace App\Services;

use App\Models\User;
use App\Mail\OtpMail;
use App\Models\Employee;
use App\Models\PersonalInformation;
use App\Models\UserOtpCode;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function employeeRegistration($data)
    {
        //First we have to take the employee_number and take the row id from employee table
        //then save the data to users table

        //Check if the employee number exists
        $employee = Employee::where('employee_number', $data['employee_number'])
            ->first('id');

        if(!$employee){
            return redirect()->back()->withErrors(['errors' => 'The Employee ID you entered is not found in our system. Please verify your ID number or contact HR for confirmation.']);
        }

        //Check if the email exists
        $email = Employee::where('employee_id', $employee->id)
            ->where('email', $data['email'])
            ->first();

        if(!$email){
            return redirect()->back()->withErrors(['errors' => 'The Email address you entered does not match the record associated with this Employee ID. Please use the official company email registered with HR.']);
        }

        $user = User::create([
            'employee_id' => $employee->id,
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        $role = Role::where('name', 'employee')->first();

        if ($role) {
            $user->assignRole($role);
        } else {
            throw new \Exception('Role "employee" not found.');
        }

        return $user;
    }

    public function sendOtpToEmail($request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'employee_number' => 'required|exists:employees,employee_number'
        ]);

        //Check if the employee number exists and email exists

        $employee = Employee::where('employee_number', $data['employee_number'])->first();
        $email = Employee::where('employee_id', $employee->id)->where('email', $data['email'])->first();
        if(!$email){
            return redirect()->back()->withErrors(['errors' => 'The Email address you entered does not match the record associated with this Employee ID. Please use the official company email registered with HR.']);
        }

        // Check the employee if there is an existing user account
        $isUserExists = Employee::with('user', 'personalInformation')->where('employee_number', $data['employee_number'])->first();

        if (!$isUserExists->user->isEmpty()) {
            return redirect()->back()->withErrors(['errors' => 'You already have an account.']);
        }

        $recentRequests = UserOtpCode::where('employee_number', $data['employee_number'])
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        // dd($recentRequests);

        if ($recentRequests >= 3) {
            return redirect()->back()->withErrors(['errors' => 'Too many OTP requests. Try again later.']);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Save hashed OTP
        UserOtpCode::create([
            'employee_number' => $request->employee_number,
            'otp' => Hash::make($otp),
            'action' => 'register',
            'expires_at' => now()->addMinutes(5), // valid for 5 minutes
        ]);

        // dd("TEST");

        $subject = "Account Registration";
        $message = "Thank you for registering with the Don Mariano Marcos Memorial State University (DHRMS) portal. To complete your registration, please use the one-time pin (OTP) provided below.";

        // Send OTP to email
        Mail::to($data['email'])->queue(new OtpMail($otp, $subject, $message));
    }

    public function resendOtp($request){
        $data = $request->validate([
            'email' => 'required|email',
            'employee_number' => 'required|exists:employees,employee_number'
        ]);

        // // Check the employee if there is an existing user account
        // $isUserExists = Employee::with('user', 'personalInformation')->where('employee_number', $data['employee_number'])->first();

        // if (!$isUserExists->user->isEmpty()) {
        //     return redirect()->back()->withErrors(['errors' => 'You already have an account.']);
        // }


        $checkOtpLatest = UserOtpCode::where('employee_number', $data['employee_number'])
            ->where('used', 0)
            ->latest()
            ->update(['used' => 1]);

        if(!$checkOtpLatest){
            return redirect()->back()->withErrors(['errors' => 'Something went wrong']);
        }

        $recentRequests = UserOtpCode::where('employee_number', $data['employee_number'])
            ->where('created_at', '>=', now()->subMinutes(15))
            ->where('used', 0)
            ->count();

        // dd($recentRequests);

        if ($recentRequests >= 3) {
            return redirect()->back()->withErrors(['errors' => 'Too many OTP requests. Try again later.']);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Save hashed OTP
        UserOtpCode::create([
            'employee_number' => $request->employee_number,
            'otp' => Hash::make($otp),
            'action' => 'register',
            'expires_at' => now()->addMinutes(5), // valid for 5 minutes
        ]);

        // dd("TEST");

        $subject = "Account Registration";
        $message = "Thank you for registering with the Don Mariano Marcos Memorial State University (DHRMS) portal. To complete your registration, please use the one-time pin (OTP) provided below.";

        // Send OTP to email
        Mail::to($data['email'])->queue(new OtpMail($otp, $subject, $message));
    }

    public function verifyOtp($data): bool
    {
        $otp = $data['otp'];
        $employee_number = $data['employee_number'];

        $otpRecord = UserOtpCode::where('employee_number', $employee_number)
            ->where('action', 'register')
            ->where('used', 0) // ensure OTP not used yet
            ->latest()
            ->first();

        if (!$otpRecord) {
            return false;
        }

        // Check if expired
        if ($otpRecord->expires_at->isPast()) {
            return false;
        }

        // Validate OTP
        if (!Hash::check($otp, $otpRecord->otp)) {
            return false;
        }

        // Mark OTP as used ONLY if valid
        $otpRecord->update(['used' => 1]);

        return true;
    }

}
