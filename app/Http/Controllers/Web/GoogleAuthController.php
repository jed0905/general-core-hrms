<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\PersonalInformation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\GoogleAuthUserCredentials;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(Request $request)
    {
        // dd($request->all());
        if (request()->has('error')) {
            // dd("TEST");
            // Example: error=access_denied
            return redirect()
                ->route('/')
                ->with('error', 'Google authentication was cancelled.');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $personalInformation = PersonalInformation::with('employee.user')
                ->where('email', $googleUser->email)->first();
            // dd($personalInformation->employee->user->first());

            if (!$personalInformation) {
                return redirect()->route('/')->with('error', 'No user associated with this Google account.');
            }

            // Initialize user variable
            $user = $personalInformation->employee->user->first();

            if (!$user) {

                $password = Str::random(24);

                // Create user
                $user = User::create([
                    'employee_id' => $personalInformation->employee->id,
                    'username' => $personalInformation->email,
                    'password' => Hash::make($password),
                    'google_id' => $googleUser->id,
                ]);

                // Send email
                Mail::to($googleUser->email)->queue(new GoogleAuthUserCredentials(
                    $personalInformation->firstname . ' ' . $personalInformation->lastname,
                    $user->username,
                    $password
                ));

                // Assign role
                $role = Role::where('name', 'employee')->first();

                if ($role) {
                    $user->assignRole($role);
                } else {
                    throw new \Exception('Role "employee" not found.');
                }
            } else {
                // Update Google credentials if needed
                $user->update([
                    'google_id' => $googleUser->id,
                ]);
            }

            // Log in the user safely
            Auth::login($user);

            if ($user->hasRole('employee')) {
                return redirect()->route('self-service.dashboard.index')
                    ->with('status', 'Successfully logged in with Google.');
            } elseif (
                $user->hasRole('superadmin') || $user->hasRole('hr_director') ||
                $user->hasRole('campus_hr')
            ) {
                return redirect()->route('dashboard.index')
                    ->with('status', 'Successfully logged in with Google.');
            } else {
                return redirect()->route('self-service.my-dtr.index')
                    ->with('status', 'Successfully logged in with Google.');
            }
        } catch (\Exception $e) {
            return redirect()
                ->route('/')
                ->with('error', 'Authentication failed: ' . $e->getMessage());
        }
    }
}
