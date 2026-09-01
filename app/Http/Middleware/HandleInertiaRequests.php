<?php

namespace App\Http\Middleware;

use App\Helpers\UserInformationHelper;
use App\Http\Controllers\Web\Administration\Organization\OrganizationGeneralController;
use App\Models\CorporateBranding;
use App\Models\OrganizationGeneralInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {

        // Skip heavy props when user is not logged in or still logging in
        if ($request->routeIs('auth.login')) {
            return [
                'csrf_token' => $request->session()->token(),
                'company_name' => config('app.company_name'),
                'company_shortcut' => config('app.company_shortcut'),
                'app_name' => config('app.name'),
                'app_fullname' => config('app.fullname'),
                'version' => config('app.version'),
            ];
        }

        return array_merge(parent::share($request), [
            'csrf_token' => $request->session()->token(),
            'app_name' => config('app.name'),
            'app_fullname' => config('app.fullname'),

            'version' => function () {
                $tag = trim(exec('git describe --tags --abbrev=0'));
                $tag = $tag ?: 'v0.0.0';

                // $commitDate = trim(exec('git log -1 --format=%cd --date=iso'));
                // $commitDate = $commitDate ?: now()->toDateTimeString();

                // return [
                //     'tag' => $tag,
                //     'date' => $commitDate,
                // ];
                return $tag;
            },

            'auth' => [
                'user' => $request->user(),
                'initials' => UserInformationHelper::getInitials(),
                'name' => UserInformationHelper::getFullName(),
                'operating_unit' => UserInformationHelper::getUserOperatingUnit(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],

            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
                'psaLoadedData' => $request->session()->get('psaLoadedData'),
            ],
            'branding' => function () {
                $branding = CorporateBranding::first();
                return $branding ? [
                    'client_logo'     => $branding->client_logo ? Storage::url($branding->client_logo) : null,
                    'primary_color'   => $branding->primary_color ?? '#1867C0',
                    'secondary_color' => $branding->secondary_color ?? '#F5F5F5',
                ] : null;
            },
            'company' => function () {
                $company = OrganizationGeneralInformation::first();
                return $company ? [
                    'name' => $company->name,
                    'shortcut' => $company->shortcut,
                    'address' => $company->street1 . ', ' . $company->city . ', ' . $company->state . ', ' . $company->country,
                ] : null;
            }


        ]);
    }
}
