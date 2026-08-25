<?php

namespace App\Jobs;

use App\Mail\TardinessMail;
use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\TardinessNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class CheckTardinessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $employeeId;

    public function __construct($employeeId = null)
    {
        $this->employeeId = $employeeId;
    }
    /**
     * Create a new job instance.
     */
    public function handle(): void
    {
        $month = now()->month;
        $year = now()->year;

        $employees = $this->employeeId
            ? Employee::where('id', $this->employeeId)->get()
            : Employee::all();

        if ($this->employeeId) {

            // 👉 If testing specific employee, keep it simple
            $employees = Employee::where('id', $this->employeeId)->get();

            foreach ($employees as $employee) {
                $this->processEmployee($employee, $month, $year);
            }

        } else {
            // 👉 Production: chunk all employees
            Employee::chunk(100, function ($employees) use ($month, $year) {

                foreach ($employees as $employee) {
                    $this->processEmployee($employee, $month, $year);
                }

            });
        }
    }

    public function processEmployee($employee, $month, $year)
    {
        $tardinessCount = 0;

        $biometricIds = \App\Models\EmployeeBiometricId::where('employee_id', $employee->id)
            ->pluck('biometric_id');

        $logs = EmployeeAttendanceLog::query()
            ->whereIn('employee_id', $biometricIds)
            ->where('direction', 'check in')
            ->whereYear('auth_date', $year)
            ->whereMonth('auth_date', $month)
            ->orderBy('auth_date_time')
            ->get()
            ->groupBy('auth_date');


        foreach ($logs as $date => $dayLogs) {

            // 🕒 Always get earliest check-in of the day (safe)
            $firstCheckIn = $dayLogs->sortBy('auth_date_time')->first();

            // 📅 Get day name (e.g. Monday)
            $dayName = Carbon::parse($date)->format('l');

            $schedule = $employee->dailyShiftSchedules()
                ->where('day_of_week', $dayName)
                ->first();

            if (!$schedule || !$schedule->time_in) {
                continue;
            }

            $scheduledTime = Carbon::parse($date . ' ' . $schedule->time_in)->seconds(0);
            $actualTime = Carbon::parse($firstCheckIn->auth_date_time)->seconds(0);

            if ($actualTime->gt($scheduledTime)) {
                $tardinessCount++;
            }

        }

        // 🚫 skip if below threshold
        if ($tardinessCount < 5) {
            return;
        }

        // 📌 track monthly notifications
        $record = TardinessNotification::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
            ],
            [
                'last_notified_count' => 0
            ]
        );

        // 📧 send only if new increment
        \DB::transaction(function () use ($record, $tardinessCount, $employee, $year) {

            $lockedRecord = TardinessNotification::where('id', $record->id)
                ->lockForUpdate()
                ->first();

            if ($tardinessCount > $lockedRecord->last_notified_count) {

                $email = optional($employee->personalInformation)->email;

                if (!$email) {
                    \Log::warning('Missing email', [
                        'employee_id' => $employee->id
                    ]);
                    return;
                }

                Mail::to($email)->queue(new TardinessMail(
                    $employee,
                    $tardinessCount,
                    now()->format('F'),
                    $year
                ));

                $lockedRecord->update([
                    'last_notified_count' => $tardinessCount
                ]);
            }
        });
    }
}
