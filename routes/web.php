<?php

use Inertia\Inertia;
use Illuminate\Http\Request;
use Laravel\Socialite\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Web\MyAccountController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Http\Controllers\Web\AuthenticationController;
use App\Http\Controllers\Web\Home\HomeDashboardController;
use App\Http\Controllers\Web\SelfService\MyLeaveController;
use App\Http\Controllers\Web\HrManagement\EmployeeController;
use App\Http\Controllers\Web\SelfService\MyProfileController;
use App\Http\Controllers\Web\SelfService\SelfServiceDashboard;
use App\Http\Controllers\Web\Maintenance\OperatingUnitController;
use App\Http\Controllers\Web\SelfService\MyEntitlementController;
use App\Http\Controllers\Web\SelfService\MyLeaveReportsController;
use App\Http\Controllers\Web\Maintenance\MaintenanceRoleController;
use App\Http\Controllers\Web\SelfService\DailyTimeRecordController;
use App\Http\Controllers\Web\User\Dashboard\UserDashboardController;
use App\Http\Controllers\Web\Administration\UserManagementController;
use App\Http\Controllers\Web\Maintenance\MaintenanceLeavesController;
use App\Http\Controllers\Web\Maintenance\MaintenanceAccountsController;
use App\Http\Controllers\Web\RolesAndPermissions\PermissionsController;
use App\Http\Controllers\Web\Maintenance\MaintenanceDashboardController;
use App\Http\Controllers\Web\Maintenance\MaintenancePositionsController;
use App\Http\Controllers\Web\HrAndAccountManagement\Leave\LeaveController;
use App\Http\Controllers\Web\Maintenance\MaintenanceDepartmentsController;
use App\Http\Controllers\Web\Maintenance\MaintenanceJobStatusesController;
use App\Http\Controllers\Web\Maintenance\MaintenanceDesignationsController;
use App\Http\Controllers\Web\Maintenance\MaintenanceEmployeeTypeController;
use App\Http\Controllers\Web\RolesAndPermissions\RolePermissionsController;
use App\Http\Controllers\Web\EmployeeMaintenance\EmployeeDashboardController;
use App\Http\Controllers\Web\Maintenance\MaintenanceOperatingUnitsController;
use App\Http\Controllers\Web\HrAndAccountManagement\Leave\LeaveTypeController;
use App\Http\Controllers\Web\Maintenance\MaintenanceEmployementStatusController;
use App\Http\Controllers\Web\HrAndAccountManagement\Leave\LeaveEntitlementController;
use App\Http\Controllers\Web\HrAndAccountManagement\Account\AccountManagementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Login Route
Route::get('/', function () {
    // dd(Auth::id());
    if (Auth::check()) {
        $user = Auth::user();

        if ($user->hasRole('employee')) {
            return redirect()->route('self-service.dashboard.index');
        } elseif ($user->hasRole('superadmin')) {
            return redirect()->route('administration.user.index');
        } else {
            return redirect()->route('self-service.my-dtr.index');
        }
    }

    return Inertia::render('auth/Index');
})->name('/');

// Google OAuth Routes
Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');


Route::get('/registration', function () {
    return Inertia::render('auth/RegistrationForm');
})->name('auth.registration');

Route::post('/registration', [AuthenticationController::class, 'employeeRegistration'])
    ->name('auth.employeeRegistration');

Route::get('/forgot-password', [ForgotPasswordController::class, 'forgotPassword'])
    ->name('forgot-password');

Route::post('/forgot-password/resetLink', [ForgotPasswordController::class, 'sendResetLink'])
    ->name('sendResetLink');

Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'passwordResetForm'])
    ->name('/resetPassword');

Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->name('updatePassword');

Route::post('/send-otp', [AuthenticationController::class, 'sendOtpToEmail'])
    ->name('auth.sendOtp');

Route::post('/resend-otp', [AuthenticationController::class, 'resendOtp'])
    ->name('auth.resendOtp');


Route::post('/verify-otp', [AuthenticationController::class, 'verifyOtp'])
    ->name('auth.verifyOtp');

Route::get('/terms-and-services', function () {
    return Inertia::render('auth/TermsAndServices');
})->name('terms-and-services');

Route::get('/privacy-policies', function () {
    return Inertia::render('auth/PrivacyPolicies');
})->name('privacy-policies');


Route::middleware('guest')->post('/login', [AuthenticationController::class, 'authenticate'])->name('auth.login');
Route::middleware('auth')->post('/logout', [AuthenticationController::class, 'logout'])->name('auth.logout');


Route::middleware(['auth', 'web'])->group(function () {


    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/my-account', [MyAccountController::class, 'index'])->name('my-account.index');
    Route::post('/my-account/change-username', [MyAccountController::class, 'changeUsername'])->name('my-account.change-username');
    Route::post('/my-account/change-password', [MyAccountController::class, 'changePassword'])->name('my-account.change-password');
    Route::post('/my-account/update-password', [MyAccountController::class, 'updatePassword'])->name('my-account.updatePassword');
    Route::post('/my-account/send-otp', [MyAccountController::class, 'sendActivateOtpRequest'])->name('my-account.send-two-factor-otp');
    Route::post('/my-account/enable-two-factor', [MyAccountController::class, 'enableTwoFactorAuthentication'])->name('my-account-enable-two-factor');


    Broadcast::routes(['middleware' => ['web', 'auth']]);

    // Notifications
    Route::middleware(['auth', 'web'])->group(function () {

        Route::controller(NotificationController::class)->name('notifications.')->group(function () {
            Route::get('/notifications', 'getNotifications');
            Route::post('/notifications/mark-as-read', 'markAsRead')->name('markAsRead');
        });
    });


    // Test Broadcast Event
    Route::get('/test-broadcast', function () {
        event(new \App\Events\TestBroadcastEvent());
        return 'Broadcast sent!';
    });
});
