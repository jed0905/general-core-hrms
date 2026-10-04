<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\StoreEmployeeDocumentRequest;
use App\Http\Requests\People\UpdateEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee documents on the private disk. Routes are scope-bound, so a
 * document can only be reached through the employee it belongs to.
 */
class EmployeeDocumentController extends Controller
{
    public function __construct(protected EmployeeDocumentService $documentService) {}

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): RedirectResponse
    {
        $this->documentService->upload(
            $employee,
            $request->file('file'),
            $request->safe()->except('file'),
            $request->user()
        );

        return back()->with('success', 'Document uploaded.');
    }

    public function update(UpdateEmployeeDocumentRequest $request, Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $this->documentService->update($document, $request->validated());

        return back()->with('success', 'Document updated.');
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($document);

        return back()->with('success', 'Document deleted.');
    }

    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->documentService->download($document);
    }

    public function view(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->documentService->inline($document);
    }
}
