<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ConvertApplicationRequest;
use App\Models\Application;
use App\Services\Recruitment\ApplicationConversionService;
use Illuminate\Http\RedirectResponse;

/**
 * Hand-off to Core HR, started from the application page.
 */
class ApplicationConversionController extends Controller
{
    public function __construct(protected ApplicationConversionService $conversions) {}

    public function store(ConvertApplicationRequest $request, Application $application): RedirectResponse
    {
        $conversion = $this->conversions->convert($application, $request->validated(), $request->user());

        return back()->with('success', $conversion->conversion_type === 'new_employee'
            ? "Converted: employee {$conversion->employee_number} created."
            : "Converted: linked to existing employee {$conversion->employee_number}.");
    }
}
