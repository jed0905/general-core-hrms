<?php

namespace App\Services;

use App\Models\CorporateBranding;
use App\Models\OrganizationGeneralInformation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrganizationService
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    /**
     * Retrieve current organization data for initial component state.
     */
    public function getOrganizationData(): array
    {
        return [
            'initialGeneral'   => OrganizationGeneralInformation::first() ?? [],
            'initialBranding'  => CorporateBranding::first() ?? [],
            'initialLocations' => $this->locationService->getAllLocations(),
        ];
    }

    /**
     * Update or create General Information.
     */
    public function updateGeneralInfo(array $data): OrganizationGeneralInformation
    {
        $info = OrganizationGeneralInformation::first();

        if ($info) {
            $info->update($data);
            return $info;
        }

        return OrganizationGeneralInformation::create($data);
    }

    /**
     * Upload client logo and remove the old file if present.
     */
    /**
     * Upload client logo and remove the old file if present.
     */
    public function updateBranding(
        ?UploadedFile $file = null,
        string $primaryColor = '#1867C0',
        string $secondaryColor = '#5C6BC0'
    ): CorporateBranding {
        $branding = CorporateBranding::first() ?? new CorporateBranding();

        // Ensure client_logo is initialized if creating a new record without a file upload
        if (!$branding->exists && !$file) {
            $branding->client_logo = '';
        }

        // Only process logo replacement if a new file was actually uploaded
        if ($file) {
            if ($branding->client_logo && Storage::disk('public')->exists($branding->client_logo)) {
                Storage::disk('public')->delete($branding->client_logo);
            }

            $branding->client_logo = $file->store('branding', 'public');
        }

        // Update theme colors
        $branding->primary_color = $primaryColor;
        $branding->secondary_color = $secondaryColor;

        $branding->save();

        return $branding;
    }
}
