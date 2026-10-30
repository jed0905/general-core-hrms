<?php

use App\Services\Payroll\Localizations\PH\PhilippinesLocalization;

return [

    /*
    |--------------------------------------------------------------------------
    | Payroll engine version
    |--------------------------------------------------------------------------
    |
    | Stored on every calculated run and payslip snapshot. Bump it when the
    | calculation pipeline changes in a way that would change results.
    |
    */

    'engine_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Country localizations
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2 code => class implementing
    | App\Services\Payroll\Contracts\PayrollLocalization. A pay group whose
    | payroll country has no entry here can't be approved.
    |
    */

    'localizations' => [
        'PH' => PhilippinesLocalization::class,
    ],

];
