<?php

namespace App\Http\Controllers\Web\Reports;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\OrganizationGeneralInformation;
use App\Services\Reports\Report;
use App\Services\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Web, Excel, PDF and print all come from the same report object, the same
 * validated filters and the same authorization (route can: + ReportFilterRequest).
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('app/Reports/Index', [
            'categories' => ReportRegistry::catalogFor($request->user()),
        ]);
    }

    public function show(ReportFilterRequest $request): Response
    {
        $report = $request->report();
        $filters = $request->filters();

        return Inertia::render('app/Reports/Show', [
            'report' => [
                'key' => $report::key(),
                'title' => $report->title(),
                'description' => $report->description(),
                'category' => ReportRegistry::CATEGORIES[$report::category()]['title'],
            ],
            'filterSchema' => $report->filters(),
            'filters' => $filters,
            'columns' => $report->columns($filters),
            'sortable' => array_keys($report->sortable()),
            'rows' => $report->hasRows($filters) ? $report->paginate($filters, $request->perPage()) : null,
            'summary' => $report->summary($filters),
            'notes' => $report->notes(),
            'pdfRowLimit' => Report::PDF_ROW_LIMIT,
            'perPageOptions' => Report::PER_PAGE_OPTIONS,
            'canExport' => $request->user()->can('report.export'),
        ]);
    }

    public function excel(ReportFilterRequest $request): BinaryFileResponse
    {
        $report = $request->report();

        return Excel::download(
            new ReportExport($report, $request->filters(), $this->context($request)),
            $this->filename($report, 'xlsx')
        );
    }

    public function pdf(ReportFilterRequest $request): HttpResponse
    {
        $report = $request->report();
        $document = $this->document($report, $request->filters(), $request);

        return Pdf::loadView('reports.document', $document)
            ->setPaper('a4', count($document['columns']) > 6 ? 'landscape' : 'portrait')
            ->download($this->filename($report, 'pdf'));
    }

    public function print(ReportFilterRequest $request): View
    {
        return view('reports.document', array_merge($this->document($request->report(), $request->filters(), $request), ['print' => true]));
    }

    /**
     * The dataset behind PDF and print (capped; larger results go to Excel).
     */
    public function document(Report $report, array $filters, Request $request): array
    {
        $rows = [];

        if ($report->hasRows($filters)) {
            foreach ($report->rows($filters) as $row) {
                $rows[] = $row;

                if (count($rows) > Report::PDF_ROW_LIMIT) {
                    abort(422, 'This report has more than '.number_format(Report::PDF_ROW_LIMIT).' rows. Narrow the filters or export to Excel.');
                }
            }
        }

        return $this->context($request) + [
            'title' => $report->title(),
            'description' => $report->description(),
            'filters' => $report->describeFilters($filters),
            'columns' => $report->columns($filters),
            'rows' => $rows,
            'hasRows' => $report->hasRows($filters),
            'summary' => $report->summary($filters),
            'notes' => $report->notes(),
            'print' => false,
        ];
    }

    private function context(Request $request): array
    {
        return [
            'organization' => OrganizationGeneralInformation::value('name') ?? config('app.name'),
            'generatedAt' => now()->format('M j, Y g:i A'),
            'generatedBy' => $request->user()->username,
        ];
    }

    private function filename(Report $report, string $extension): string
    {
        return $report::key().'_'.now()->format('Ymd_His').'.'.$extension;
    }
}
