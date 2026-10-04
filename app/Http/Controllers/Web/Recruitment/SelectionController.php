<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\RecordSelectionRequest;
use App\Models\Application;
use App\Models\ApplicationSelection;
use App\Services\Recruitment\SelectionService;
use Illuminate\Http\RedirectResponse;

/**
 * Selection decisions are made from the application page.
 */
class SelectionController extends Controller
{
    public function __construct(protected SelectionService $selections) {}

    public function store(RecordSelectionRequest $request, Application $application): RedirectResponse
    {
        $selection = $this->selections->decide($application, $request->validated('decision'), $request->validated('remarks'), $request->user());

        return back()->with('success', $selection->decision === ApplicationSelection::SELECTED ? 'Candidate selected.' : 'Candidate marked not selected.');
    }
}
