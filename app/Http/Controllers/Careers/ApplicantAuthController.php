<?php

namespace App\Http\Controllers\Careers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Careers\ApplicantLoginRequest;
use App\Http\Requests\Careers\ApplicantPasswordResetRequest;
use App\Http\Requests\Careers\CompleteApplicantRegistrationRequest;
use App\Http\Requests\Careers\PublicApplicantRegistrationRequest;
use App\Models\ApplicantAccount;
use App\Services\Careers\ApplicantAccountService;
use App\Services\Recruitment\ApplicantService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Candidate sign-up, sign-in and password reset (guard "applicant", broker
 * "applicant_accounts"). Responses never reveal whether an email is known.
 */
class ApplicantAuthController extends Controller
{
    private const GENERIC_SENT = 'If that email address can be used, we have sent you a link. Please check your inbox (and spam folder).';

    public function __construct(protected ApplicantAccountService $accounts) {}

    public function showRegister(): Response|RedirectResponse
    {
        return $this->signedIn() ?? Inertia::render('careers/auth/Register');
    }

    public function register(PublicApplicantRegistrationRequest $request): RedirectResponse
    {
        $this->accounts->register($request->safe()->except('privacy_consent'));

        return redirect()->route('careers.login')->with('status', self::GENERIC_SENT);
    }

    public function resend(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:191']]);
        $this->accounts->resend($request->string('email'));

        return back()->with('status', self::GENERIC_SENT);
    }

    public function showComplete(Request $request, ApplicantAccount $account): Response|RedirectResponse
    {
        $this->assertLinkMatches($request, $account);

        if ($account->isActivated()) {
            return redirect()->route('careers.login')->with('status', 'Your account is already set up. Please sign in.');
        }

        return Inertia::render('careers/auth/Complete', ['email' => $account->email, 'action' => $request->fullUrl()]);
    }

    public function complete(CompleteApplicantRegistrationRequest $request, ApplicantAccount $account): RedirectResponse
    {
        $this->assertLinkMatches($request, $account);
        $this->accounts->complete($account, $request->validated('password'));

        return redirect()->route('careers.applications.index')->with('success', 'Your account is ready.');
    }

    public function showLogin(): Response|RedirectResponse
    {
        return $this->signedIn() ?? Inertia::render('careers/auth/Login', ['status' => session('status')]);
    }

    public function login(ApplicantLoginRequest $request): RedirectResponse
    {
        if (! $this->accounts->attempt($request->validated('email'), $request->validated('password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => ['These details don\'t match an active account. If you just registered, use the link we emailed you first.']]);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('careers.applications.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('applicant')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('careers.index');
    }

    public function showForgot(): Response
    {
        return Inertia::render('careers/auth/ForgotPassword', ['status' => session('status')]);
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:191']]);
        Password::broker('applicant_accounts')->sendResetLink([
            'email' => ApplicantService::normalizeEmail($request->string('email')),
            fn ($q) => $q->whereNotNull('applicant_id'),
        ]);

        return back()->with('status', self::GENERIC_SENT);
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('careers/auth/ResetPassword', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(ApplicantPasswordResetRequest $request): RedirectResponse
    {
        $status = Password::broker('applicant_accounts')->reset(
            ['email' => ApplicantService::normalizeEmail($request->validated('email'))] + $request->only(['password', 'password_confirmation', 'token']),
            function (ApplicantAccount $account, string $password) {
                $account->forceFill(['password' => $password])->setRememberToken(null);
                $account->save();
                event(new PasswordReset($account));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => ['This reset link is invalid or has expired. Please request a new one.']]);
        }

        return redirect()->route('careers.login')->with('status', 'Your password was changed. Please sign in.');
    }

    protected function signedIn(): ?RedirectResponse
    {
        return Auth::guard('applicant')->user()?->isUsable() ? redirect()->route('careers.applications.index') : null;
    }

    /** The signed URL also carries a hash of the email, so a link stops working if the address changes. */
    protected function assertLinkMatches(Request $request, ApplicantAccount $account): void
    {
        abort_unless(hash_equals(sha1($account->email), (string) $request->route('hash')), 403);
    }
}
