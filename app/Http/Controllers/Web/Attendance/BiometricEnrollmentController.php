<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ReassignEnrollmentRequest;
use App\Http\Requests\Attendance\StoreEnrollmentRequest;
use App\Models\AttendanceDevice;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use App\Services\Attendance\BiometricEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee <-> device user ID mappings (identity references only; templates stay
 * on the devices). Card numbers are never sent to the page.
 */
class BiometricEnrollmentController extends Controller
{
    public function __construct(protected BiometricEnrollmentService $enrollments) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'device_id', 'status']);

        return Inertia::render('app/Attendance/Enrollments/Index', [
            'enrollments' => $this->enrollments->getPaginatedEnrollments($filters),
            'filters' => $filters,
            'devices' => AttendanceDevice::orderBy('name')->get(['id', 'name', 'vendor', 'serial_number']),
            'employees' => Employee::where('status', 'active')->orderBy('emp_last_name')->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name']),
            'can' => ['manage' => $request->user()->can('attendance.enrollment.manage')],
        ]);
    }

    public function store(StoreEnrollmentRequest $request): RedirectResponse
    {
        [, $mapped] = $this->enrollments->enroll($request->validated(), $request->user());

        return back()->with('success', 'Mapping saved.'.($mapped ? " {$mapped} earlier punches were matched to the employee." : ''));
    }

    public function deactivate(Request $request, BiometricEnrollment $biometricEnrollment): RedirectResponse
    {
        $this->enrollments->deactivate($biometricEnrollment, $request->user(), $request->string('remarks')->toString() ?: null);

        return back()->with('success', 'Mapping deactivated.');
    }

    public function activate(Request $request, BiometricEnrollment $biometricEnrollment): RedirectResponse
    {
        $this->enrollments->activate($biometricEnrollment, $request->user());

        return back()->with('success', 'Mapping activated.');
    }

    public function reassign(ReassignEnrollmentRequest $request, BiometricEnrollment $biometricEnrollment): RedirectResponse
    {
        $this->enrollments->reassign($biometricEnrollment, $request->validated('device_user_id'), $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Device user ID changed; the previous mapping is kept in the history.');
    }
}
