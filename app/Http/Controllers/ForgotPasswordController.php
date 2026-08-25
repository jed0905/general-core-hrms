<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Mail\PassswordResetLink;
use App\Models\PersonalInformation;
use App\Models\User;
use App\Models\UserOtpCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function forgotPassword()
    {
        return Inertia::render('auth/ForgotPassword');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = $request->only('email')['email'];

        $personalInfo = PersonalInformation::where('email', $email)->first();

        if (!$personalInfo) {
            // Handle gracefully without exposing sensitive info
            return redirect()->back()->with('status', 'If an account exists for this email, you will receive a reset link.');
        }

        // Get the associated user (nullable)
        $user = $personalInfo->employee->user ?? null;

        if (!$user) {
            // Handle gracefully
            return redirect()->back()->with('status', 'If an account exists for this email, you will receive a reset link.');
        }

        // Generate token and save
        $token = Str::random(64);

        $lastRequest = DB::table('password_resets')
            ->where('email', $personalInfo->email)
            ->latest('created_at')
            ->first();

        if ($lastRequest && now()->diffInMinutes($lastRequest->created_at) < 5) {
            return back()->withErrors(['error' => 'You can request another reset link in 5 minutes.']);
        }   

        DB::table('password_resets')->updateOrInsert(
            ['email' => $personalInfo->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send email manually (example)
        $resetLink = url("/reset-password/{$token}?email={$personalInfo->email}");
        Mail::to($personalInfo->email)->queue(new PassswordResetLink($resetLink));

        // Success message
        return redirect()->back()->with('status', 'If an account exists for this email, you will receive a reset link.');
    }

    public function passwordResetForm(string $token, Request $request)
    {

        $email = $request->query('email'); // e.g., /reset-password?token=XYZ&email=user@example.com

        if (!$email) {
            abort(410);
        }

        // Find the record by email
        $record = DB::table('password_resets')->where('email', $email)->first();

        if (!$record || !Hash::check($token, $record->token)) {
            // Token invalid
            abort(410);
        }

        // Check if token is expired (e.g., 5 minutes)
        $expires = Carbon::parse($record->created_at)->addMinutes(5);
        if (Carbon::now()->greaterThan($expires)) {
            abort(410);
        }

        // Token is valid, render reset form
        return Inertia::render('auth/ResetPassword', [
            'token' => $token,
            'email' => $record->email, // optional if you want to prefill
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:personal_information,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Find record by email
        $record = DB::table('password_resets')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            abort(410);
        }

        // Check expiration
        $expires = Carbon::parse($record->created_at)->addMinutes(60);
        if (Carbon::now()->greaterThan($expires)) {
            abort(410);
        }

        // Find user
        $user = User::whereHas('employee.personalInformation', function ($query) use ($request) {
            $query->where('email', $request->email);
        })->first();

        if (!$user) {
            return redirect()->route('/')
                ->withErrors(['email' => 'No user found for this email.']);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Log out all other sessions
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Delete token
        DB::table('password_resets')->where('email', $request->email)->delete();

        return redirect()->route('/')
            ->with('status', 'Your password has been reset successfully!');
    }
}
