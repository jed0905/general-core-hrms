<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Ingestion\EventIngestionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee <-> device user ID mappings. One active mapping per (device, device
 * user ID); an employee may be mapped on many devices with different IDs.
 * Mappings are deactivated, never deleted. Mapping a device user maps that
 * user's earlier unmatched events. No biometric templates are handled here.
 */
class BiometricEnrollmentService
{
    public function __construct(
        protected EventIngestionService $ingestion,
        protected AttendanceAuditLogger $audit,
    ) {}

    public function getPaginatedEnrollments(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return BiometricEnrollment::query()
            ->with(['device:id,name,vendor,serial_number', 'employee:id,employee_number,emp_first_name,emp_last_name', 'enrolledBy:id,username'])
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('attendance_device_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('device_user_id', 'like', "%{$s}%")
                ->orWhereHas('employee', fn ($e) => $e->where('employee_number', 'like', "%{$s}%")->orWhere('emp_last_name', 'like', "%{$s}%")->orWhere('emp_first_name', 'like', "%{$s}%"))))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array{0: BiometricEnrollment, 1: int} enrollment and number of earlier events mapped */
    public function enroll(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            AttendanceDevice::whereKey($data['attendance_device_id'])->lockForUpdate()->firstOrFail();
            $this->assertFree((int) $data['attendance_device_id'], $data['device_user_id']);

            $enrollment = BiometricEnrollment::create([
                ...$data,
                'status' => $data['status'] ?? BiometricEnrollment::STATUS_ACTIVE,
                'enrolled_on' => $data['enrolled_on'] ?? now()->toDateString(),
                'enrolled_by' => $user->id,
            ]);
            $this->audit->record($enrollment, 'enrolled', $user, ['device_id' => $enrollment->attendance_device_id, 'employee_id' => $enrollment->employee_id, 'device_user_id' => $enrollment->device_user_id]);

            $mapped = $enrollment->status === BiometricEnrollment::STATUS_ACTIVE ? $this->ingestion->remapUnmatched($enrollment) : 0;

            return [$enrollment, $mapped];
        });
    }

    public function deactivate(BiometricEnrollment $enrollment, User $user, ?string $remarks = null): void
    {
        if ($enrollment->status === BiometricEnrollment::STATUS_INACTIVE) {
            return;
        }

        $enrollment->update([
            'status' => BiometricEnrollment::STATUS_INACTIVE,
            'deactivated_at' => now(),
            'deactivated_by' => $user->id,
            'remarks' => $remarks ?? $enrollment->remarks,
        ]);
        $this->audit->record($enrollment, 'deactivated', $user, ['remarks' => $remarks]);
    }

    public function activate(BiometricEnrollment $enrollment, User $user): void
    {
        DB::transaction(function () use ($enrollment, $user) {
            $this->assertFree($enrollment->attendance_device_id, $enrollment->device_user_id, $enrollment->id);
            $enrollment->update(['status' => BiometricEnrollment::STATUS_ACTIVE, 'deactivated_at' => null, 'deactivated_by' => null]);
            $this->audit->record($enrollment, 'activated', $user);
            $this->ingestion->remapUnmatched($enrollment);
        });
    }

    /** The employee's device user ID changed: close the old mapping, open a new one. */
    public function reassign(BiometricEnrollment $enrollment, string $newDeviceUserId, User $user, ?string $remarks = null): BiometricEnrollment
    {
        return DB::transaction(function () use ($enrollment, $newDeviceUserId, $user, $remarks) {
            $this->deactivate($enrollment, $user, $remarks ?? "Reassigned to {$newDeviceUserId}");
            [$new] = $this->enroll([
                'attendance_device_id' => $enrollment->attendance_device_id,
                'employee_id' => $enrollment->employee_id,
                'device_user_id' => $newDeviceUserId,
                'external_reference' => $enrollment->external_reference,
                'remarks' => $remarks,
            ], $user);

            return $new;
        });
    }

    /** @return Collection<int, BiometricEnrollment> */
    public function history(Employee $employee)
    {
        return BiometricEnrollment::with(['device:id,name,vendor', 'enrolledBy:id,username'])->where('employee_id', $employee->id)->orderByDesc('id')->get();
    }

    private function assertFree(int $deviceId, string $deviceUserId, ?int $exceptId = null): void
    {
        $taken = BiometricEnrollment::where('attendance_device_id', $deviceId)
            ->where('device_user_id', $deviceUserId)
            ->where('status', '<>', BiometricEnrollment::STATUS_INACTIVE)
            ->when($exceptId, fn ($q) => $q->where('id', '<>', $exceptId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['device_user_id' => 'This device user ID is already mapped to an employee on this device. Deactivate that mapping first.']);
        }
    }
}
