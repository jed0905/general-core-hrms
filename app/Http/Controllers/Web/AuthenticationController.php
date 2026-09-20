<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use Exception;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Requests\AuthenticationRequest;
use App\Http\Requests\StoreEmployeeAccountRegistrationRequest;
use App\Services\AuthenticationService;

class AuthenticationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function authenticate(AuthenticationRequest $request)
    {
        $credentials = $request->only('username', 'password');
        $remember = $request->boolean('remember');
        $username = Str::lower($request->input('username'));
        $key = 'login:' . $username;

        $user = User::where('username', $username)->first();

        // Check if user is already suspended
        if ($user && $user->status === 'suspended') {
            return back()->withErrors([
                'error' => 'Your account is suspended. Please contact HR.'
            ]);
        }

        // Check if throttled (5 per 15 minutes)
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'error' => 'Too many login attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'
            ]);
        }

        // Attempt login with remember me functionality
        if (Auth::attempt($credentials, $remember)) {

            // if($user->is_two_factor_enabled) {

            // }

            RateLimiter::clear($key); // clear attempts

            // Reset failed logins
            if ($user) {
                $user->update(['failed_logins' => 0]);
            }

            if ($user->status === 'inactive') {
                Auth::logout();
                return back()->withErrors(['error' => 'Your account is inactive.']);
            }

            $request->session()->regenerate();

            return redirect()->route('dashboard.index');

            // if ($user->hasRole('employee')) {
            //     return redirect()->route('self-service.dashboard.index');
            // } elseif ($user->hasRole('superadmin') || $user->hasRole('hr_director') || $user->hasRole('campus_hr')) {
            //     return redirect()->route('dashboard.index');
            // } else {
            //     return redirect()->route('self-service.my-dtr.index');
            // }
        }

        // If authentication fails
        RateLimiter::hit($key, 900); // 15 mins throttle

        if ($user) {
            // Increment failed login counter in DB
            $user->increment('failed_logins');

            // Suspend account if >= 10
            if ($user->failed_logins >= 10) {
                $user->update(['status' => 'suspended']);
                return back()->withErrors([
                    'error' => 'Your account has been suspended due to multiple failed login attempts. Please contact ICT.'
                ]);
            }
        }

        return back()->withErrors(['error' => 'Invalid credentials.']);
    }

    public function employeeRegistration(StoreEmployeeAccountRegistrationRequest $request, AuthenticationService $authenticationService)
    {
        try {
            $authenticationService->employeeRegistration($request->validated());
            return redirect()->route('/');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to register employee: ' . $e->getMessage()]);
        }
    }

    public function sendOtpToEmail(Request $request, AuthenticationService $authenticationService)
    {
        try {
            $authenticationService->sendOtpToEmail($request);

            return redirect()->back();
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Failed to send otp: ' . $e->getMessage()]);
        }
    }

    public function resendOtp(Request $request, AuthenticationService $authenticationService)
    {
        try {
            $authenticationService->resendOtp($request);
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Failed to resend otp: ' . $e->getMessage()]);
        }
    }

    public function verifyOtp(StoreEmployeeAccountRegistrationRequest $request, AuthenticationService $authenticationService)
    {
        $isValid = $authenticationService->verifyOtp($request->only('employee_number', 'otp'));

        if (!$isValid) {
            return back()->withErrors(['error' => 'The OTP you entered is invalid.']);
        }

        try {
            $authenticationService->employeeRegistration($request->validated());
            return redirect('/');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to register employee: ' . $e->getMessage()]);
        }
    }


    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('/');
    }
}
