<?php

namespace App\Http\Controllers\Web\Administration\Organization;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class CorporateBrandingController extends Controller
{
    public function __construct(
        protected OrganizationService $organizationService
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_logo' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,svg',
                'max:2048',
            ],

            'primary_color' => [
                'required',
                'string',
                'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3}|[a-fA-F0-9]{8})$/i',
            ],

            'secondary_color' => [
                'required',
                'string',
                'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3}|[a-fA-F0-9]{8})$/i',
            ],
        ]);

        $this->organizationService->updateBranding(
            file: $request->file('client_logo'),
            primaryColor: $validated['primary_color'],
            secondaryColor: $validated['secondary_color']
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Corporate branding updated successfully.'
            );
    }
}
