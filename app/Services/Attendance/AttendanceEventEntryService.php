<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Devices\Support\EventTime;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use App\Services\Attendance\Engine\DayContextResolver;
use App\Services\Attendance\Ingestion\EventIngestionService;
use App\Services\Attendance\Ingestion\IngestResult;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Raw logs that don't come from a device connection: manual entries and CSV
 * imports. All go through EventIngestionService (source manual / import), so
 * they are deduplicated and calculated exactly like device events.
 */
class AttendanceEventEntryService
{
    /** Accepted CSV columns, in order: employee_number, date_time, type (optional), device_serial (optional). */
    public const CSV_COLUMNS = ['employee_number', 'date_time', 'type', 'device_serial'];

    public function __construct(
        protected EventIngestionService $ingestion,
        protected AttendanceScope $scope,
        protected DayContextResolver $contexts,
        protected AttendanceAuditLogger $audit,
    ) {}

    public function getPaginatedEvents(User $user, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = AttendanceEvent::query()
            ->with(['device:id,name,vendor', 'employee:id,employee_number,emp_first_name,emp_last_name', 'creator:id,username'])
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('local_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('local_date', '<=', $v))
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('attendance_device_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        // Unmatched/invalid events have no employee; only users who see everyone see them.
        $ids = $this->scope->employeeIds($user);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /** A punch entered by an authorized user, in the employee's attendance timezone. */
    public function createManual(Employee $employee, array $data, User $user): IngestResult
    {
        if (! $this->scope->allows($user, $employee->id)) {
            abort(403);
        }

        $timezone = $this->contexts->resolve($employee, substr($data['occurred_at'], 0, 10))->timezone;
        $occurredAt = EventTime::toUtc($data['occurred_at'], $timezone)
            ?? throw ValidationException::withMessages(['occurred_at' => 'Enter a valid date and time.']);
        $device = isset($data['attendance_device_id']) ? AttendanceDevice::find($data['attendance_device_id']) : null;

        $result = $this->ingestion->ingest(null, [new NormalizedEvent(null, null, $data['occurred_at'], $occurredAt, $data['event_type'], 'manual', ['device_id' => $device?->id])],
            'manual', null, $user, $employee->id, $timezone, $data['reason']);
        $this->audit->record($employee, 'manual_punch_created', $user, ['occurred_at' => $data['occurred_at'], 'type' => $data['event_type'], 'reason' => $data['reason']]);

        return $result;
    }

    /**
     * Import CSV rows (see CSV_COLUMNS; a header row is optional). Times are
     * read in the employee's attendance timezone unless they carry an offset.
     *
     * @return array{inserted: int, duplicates: int, errors: array<int, string>}
     */
    public function importCsv(string $csv, User $user): array
    {
        $inserted = $duplicates = 0;
        $errors = [];
        $numbers = Employee::pluck('id', 'employee_number');

        foreach (preg_split('/\r\n|\r|\n/', trim($csv)) as $index => $line) {
            $row = array_map('trim', array_pad(str_getcsv($line), 4, ''));
            if ($index === 0 && strtolower($row[0]) === 'employee_number') {
                continue;
            }
            [$number, $time, $type, $serial] = $row;
            $label = 'Line '.($index + 1).': ';

            $employeeId = $numbers[$number] ?? null;
            if (! $employeeId) {
                $errors[] = $label."unknown employee number {$number}.";

                continue;
            }
            if (! $this->scope->allows($user, (int) $employeeId)) {
                $errors[] = $label.'employee outside your access.';

                continue;
            }
            $type = $type === '' ? 'unknown' : $type;
            if (! in_array($type, AttendanceEvent::TYPES, true)) {
                $errors[] = $label."unknown type {$type}.";

                continue;
            }

            $timezone = $this->contexts->resolve(Employee::find($employeeId), substr($time, 0, 10))->timezone;
            $occurredAt = EventTime::toUtc($time, $timezone);
            if (! $occurredAt) {
                $errors[] = $label."invalid date/time {$time}.";

                continue;
            }

            $device = $serial !== '' ? AttendanceDevice::where('serial_number', $serial)->first() : null;
            $result = $this->ingestion->ingest(null, [new NormalizedEvent(null, null, $time, $occurredAt, $type, null, array_filter(['device_id' => $device?->id, 'device_serial' => $serial ?: null]))],
                'import', null, $user, (int) $employeeId, $timezone, 'CSV import');
            $inserted += $result->inserted;
            $duplicates += $result->duplicates;
        }

        return ['inserted' => $inserted, 'duplicates' => $duplicates, 'errors' => $errors];
    }

    /** CSV template download content. */
    public static function template(): string
    {
        return implode(',', self::CSV_COLUMNS)."\nEMP-00001,".CarbonImmutable::now()->format('Y-m-d').' 08:00,check_in,'."\n";
    }
}
