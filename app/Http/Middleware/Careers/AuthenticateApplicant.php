<?php

namespace App\Http\Middleware\Careers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Careers pages that need a signed-in candidate (guard "applicant"). A
 * disabled or not-yet-activated account is signed out. Employee/HR logins
 * (guard "web") never satisfy this, and this never satisfies theirs.
 */
class AuthenticateApplicant
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('applicant');
        $account = $guard->user();

        if (! $account || ! $account->isUsable()) {
            if ($account) {
                $guard->logout();
            }

            return redirect()->guest(route('careers.login'));
        }

        // The default guard stays "web": controllers read the candidate with
        // Auth::guard('applicant'), so shared HR props never see an applicant account.
        return $next($request);
    }
}
