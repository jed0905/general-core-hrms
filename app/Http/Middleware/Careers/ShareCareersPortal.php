<?php

namespace App\Http\Middleware\Careers;

use App\Models\CareersSetting;
use App\Models\OrganizationGeneralInformation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Props every careers page needs: company name and public contact (existing
 * organization settings), careers texts, and the signed-in candidate's name
 * only. Logo and colours come from the existing "branding" shared prop.
 */
class ShareCareersPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('portal', function () {
            $org = OrganizationGeneralInformation::query()->first(['name', 'email', 'phone']);
            $settings = CareersSetting::query()->orderBy('id')->first(['headline', 'introduction', 'application_instructions', 'privacy_notice']);
            $account = Auth::guard('applicant')->user();
            $applicant = $account?->isUsable() ? $account->applicant()->first(['id', 'first_name', 'last_name']) : null;

            return [
                'company' => ['name' => $org?->name ?: 'Careers', 'email' => $org?->email, 'phone' => $org?->phone],
                'settings' => $settings?->only(['headline', 'introduction', 'application_instructions', 'privacy_notice']),
                'candidate' => $applicant ? ['name' => trim("{$applicant->first_name} {$applicant->last_name}")] : null,
                // Careers pages show their own notices (the global flash share has no "success").
                'notice' => session('success') ?? session('status'),
            ];
        });

        return $next($request);
    }
}
