<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMyAccountPasswordRequest;
use App\Http\Requests\UpdateMyAccountUsernameRequest;
use App\Services\MyAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MyAccountController extends Controller
{
    public function index(MyAccountService $myAccountService)
    {
        $employee = $myAccountService->employeeInformation();
        return Inertia::render('app/MyAccount/Index', [
            'employee' => $employee,
        ]);
    }

    public function changeUsername(UpdateMyAccountUsernameRequest $request, MyAccountService $myAccountService)
    {
        try {
            $myAccountService->changeUsername($request->validated());
            return redirect()->route('/');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to change username: ' . $e->getMessage()]);
        }
    }

    public function changePassword(MyAccountService $myAccountService)
    {
        try {
            $myAccountService->changePassword();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update password: ' . $e->getMessage()]);
        }
    }

    public function updatePassword(UpdateMyAccountPasswordRequest $request, MyAccountService $myAccountService)
    {
        try {
            $myAccountService->updatePassword($request->validated(), $request['otp']);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update password: ' . $e->getMessage()]);
        }
    }

    public function sendActivateOtpRequest(MyAccountService $myAccountService)
    {
        try {
            $myAccountService->sendActivateOtp();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to send OTP: ' . $e->getMessage()]);
        }
    }

    public function enableTwoFactorAuthentication(Request $request, MyAccountService $myAccountService)
    {
        try {
            $myAccountService->activateTwoFactorAuthentication($request->otp);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update password: ' . $e->getMessage()]);
        }
    }
}
