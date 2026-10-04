<?php

namespace Database\Seeders\Demo;

use App\Models\Applicant;
use App\Models\CorporateBranding;
use App\Models\Employee;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeBiometricId;
use App\Models\EmployeeMovementType;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Services\DepartmentService;
use App\Services\EmployeeMovementService;
use App\Services\EmployeeService;
use App\Services\EmployeeWorkScheduleService;
use App\Services\EmploymentStatusService;
use App\Services\HolidayService;
use App\Services\JobTitleService;
use App\Services\Leave\LeaveApplicationService;
use App\Services\Leave\LeaveApprovalService;
use App\Services\Leave\LeaveApprovalWorkflowService;
use App\Services\LeaveBalanceService;
use App\Services\LeavePolicyRuleService;
use App\Services\LeavePolicyService;
use App\Services\LeaveTypeService;
use App\Services\LocationService;
use App\Services\OrganizationService;
use App\Services\ShiftService;
use App\Services\UserService;
use App\Services\WorkScheduleService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Database\Seeders\EmployeeMovementTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Fictional "Acme Digital Solutions, Inc." demo environment.
 *
 *   php artisan db:seed --class="Database\Seeders\Demo\AcmeDemoSeeder"
 *
 * Everything goes through the application's services (workflow resolution,
 * approvals, balance movements, employee movements), with authorization
 * checked before approvals/cancellations. Historic events are recorded at
 * their own dates by moving the clock (Carbon::setTestNow) during seeding.
 *
 * Runs once: it refuses to run if the Acme employees already exist. All
 * writes happen in one transaction. Existing records are not deleted.
 *
 * Recruitment (SeedsAcmeRecruitment) is seeded in its own transaction after
 * the Core HR data, also once. On a database that already has the Acme Core
 * HR data but no recruitment demo, only the recruitment demo is added.
 */
class AcmeDemoSeeder extends Seeder
{
    use SeedsAcmeRecruitment;

    private const MARKER = 'ACME-0001';

    /** Last day with attendance punches; the demo "today" is the real today. */
    private const ATTENDANCE_FROM = '2026-09-07';

    private const ATTENDANCE_TO = '2026-10-02';

    public string $password = '';

    private array $locations = [];

    private array $departments = [];

    private array $titles = [];

    private array $statuses = [];

    private array $shifts = [];

    private array $schedules = [];

    private array $leaveTypes = [];

    /** @var array<string, Employee> */
    private array $employees = [];

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The Acme demo seeder must not run in production.');
        }

        $coreExists = Employee::where('employee_number', self::MARKER)->exists();
        $recruitmentExists = Applicant::where('normalized_email', self::RECRUITMENT_MARKER)->exists();

        if ($coreExists && $recruitmentExists) {
            $this->command?->warn('Acme demo data already exists; nothing was changed.');

            return;
        }

        $this->prerequisites();

        try {
            if ($coreExists) {
                $this->loadCore();
            } else {
                $this->password = 'Acme-'.Str::password(10, symbols: false).'!';
                mt_srand(20261003);

                DB::transaction(function () {
                    $this->organization();
                    $this->structure();
                    $this->timeConfiguration();
                    $this->people();
                    $this->accounts();
                    $this->movements();
                    $this->scheduleAssignments();
                    $this->leaveConfiguration();
                    $this->leaveBalances();
                    $this->leaveScenarios();
                    $this->attendance();
                });

                $this->command?->info('Acme demo environment created.');
                $this->command?->info('Demo password for every Acme account: '.$this->password);
            }

            $this->recruitment();
        } finally {
            Carbon::setTestNow();
        }

        if ($coreExists) {
            $this->command?->info('Demo password for the new careers-portal accounts: '.$this->password);
        }
    }

    /**
     * Reference data the demo relies on (both seeders are idempotent).
     */
    private function prerequisites(): void
    {
        if (! Role::where('name', 'hr_director')->where('guard_name', 'web')->exists()) {
            $this->call(RolesAndPermissionsSeeder::class);
        }

        if (! EmployeeMovementType::where('code', 'hiring')->exists()) {
            $this->call(EmployeeMovementTypeSeeder::class);
        }
    }

    /**
     * The Acme employees and accounts from an earlier run (same keys as roster() and accountPlan()).
     */
    private function loadCore(): void
    {
        $n = 0;

        foreach (array_keys($this->roster()) as $key) {
            $this->employees[$key] = Employee::where('employee_number', sprintf('ACME-%04d', ++$n))->firstOrFail();
        }

        foreach ($this->accountPlan() as $key => [$username]) {
            $this->users[$key] = User::where('username', $username)->firstOrFail();
        }
    }

    // ------------------------------------------------------------------
    // Organization, locations, departments, job titles, statuses
    // ------------------------------------------------------------------

    private function organization(): void
    {
        app(OrganizationService::class)->updateGeneralInfo([
            'name' => 'Acme Digital Solutions, Inc.',
            'shortcut' => 'ACME',
            'phone' => '+63 2 8555 0100',
            'email' => 'hello@acmedigital.example',
            'country' => 'Philippines',
            'province' => 'Metro Manila',
            'city' => 'Pasig City',
            'zip_code' => '1605',
            'street1' => '18F Acme Tower, 1200 Innovation Avenue',
            'street2' => 'Ortigas Business District',
            'note' => 'Fictional company used for product demonstrations.',
        ]);

        // The previous logo belonged to another organization. client_logo is
        // required, so point it at a generated, fictional Acme logo instead
        // (the old file stays on disk).
        CorporateBranding::query()->update(['client_logo' => $this->acmeLogo()]);
    }

    /**
     * Draws a simple fictional logo and stores it on the public disk.
     */
    private function acmeLogo(): string
    {
        $path = 'branding/acme-demo-logo.png';
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        $image = imagecreatetruecolor(512, 512);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, 256, 256, 500, 500, imagecolorallocate($image, 24, 103, 192));
        imagefilledellipse($image, 256, 256, 440, 440, imagecolorallocate($image, 255, 255, 255));
        imagefilledellipse($image, 256, 256, 420, 420, imagecolorallocate($image, 24, 103, 192));

        $white = imagecolorallocate($image, 255, 255, 255);
        foreach ([['ACME', 96, 290], ['DIGITAL', 36, 350]] as [$text, $size, $y]) {
            $box = imagettfbbox($size, 0, $font, $text);
            imagettftext($image, $size, 0, (int) (256 - ($box[2] - $box[0]) / 2), $y, $white, $font, $text);
        }

        ob_start();
        imagepng($image);
        Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($image);

        return $path;
    }

    private function structure(): void
    {
        $locationService = app(LocationService::class);

        foreach ([
            'main' => ['Main Corporate Office, 18F Acme Tower, 1200 Innovation Avenue', 'Pasig City', 'Metro Manila', '1605', '+63 2 8555 0100', true],
            'north' => ['North Branch, 3F Northpoint Plaza, 88 Mindanao Avenue', 'Quezon City', 'Metro Manila', '1106', '+63 2 8555 0200', false],
            'south' => ['South Branch, 2F Southgate Center, 15 Alabang–Zapote Road', 'Muntinlupa City', 'Metro Manila', '1780', '+63 2 8555 0300', false],
            'ops' => ['Operations Center, Bldg 4, 52 Logistics Drive', 'Taguig City', 'Metro Manila', '1630', '+63 2 8555 0400', false],
            'remote' => ['Remote / Hybrid Office', 'Pasig City', 'Metro Manila', '1605', null, false],
        ] as $key => [$address, $city, $province, $zip, $phone, $isMain]) {
            $this->locations[$key] = $locationService->storeLocation([
                'address' => $address, 'city' => $city, 'province' => $province, 'zip_code' => $zip,
                'phone' => $phone, 'is_main' => $isMain, 'notes' => 'Acme demo location',
            ]);
        }

        $departmentService = app(DepartmentService::class);
        $this->departments['exec'] = $departmentService->createDepartment(['name' => 'Executive Office', 'shortcut' => 'EXEC', 'parent_id' => null]);

        foreach ([
            'hr' => ['Human Resources', 'HR'],
            'fin' => ['Finance & Accounting', 'FIN'],
            'it' => ['Information Technology', 'IT'],
            'ops' => ['Operations', 'OPS'],
            'sales' => ['Sales & Marketing', 'SALES'],
            'cs' => ['Customer Support', 'CS'],
            'admin' => ['Administration', 'ADMIN'],
        ] as $key => [$name, $shortcut]) {
            $this->departments[$key] = $departmentService->createDepartment(['name' => $name, 'shortcut' => $shortcut, 'parent_id' => $this->departments['exec']->id]);
        }

        $titleService = app(JobTitleService::class);

        foreach ([
            'ceo' => ['Chief Executive Officer', 'Leads the company and sets overall strategy.'],
            'coo' => ['Chief Operating Officer', 'Oversees day-to-day operations across all business units.'],
            'hr_manager' => ['HR Manager', 'Leads the HR function: people operations, policy and employee relations.'],
            'hr_officer' => ['HR Officer', 'Handles employee records, movements and HR transactions.'],
            'hr_specialist' => ['HR Specialist', 'Specialist for recruitment, onboarding and HR programs.'],
            'hr_assistant' => ['HR Assistant', 'Supports HR administration and records.'],
            'it_manager' => ['IT Manager', 'Leads software development and IT infrastructure.'],
            'senior_dev' => ['Senior Software Developer', 'Senior engineer and team lead for product development.'],
            'software_dev' => ['Software Developer', 'Designs, builds and maintains software.'],
            'junior_dev' => ['Junior Software Developer', 'Entry-level software developer.'],
            'sysadmin' => ['Systems Administrator', 'Maintains servers, networks and internal systems.'],
            'it_support' => ['IT Support Specialist', 'Provides end-user IT support.'],
            'finance_manager' => ['Finance Manager', 'Leads finance, accounting and budgeting.'],
            'accountant' => ['Accountant', 'Prepares books of accounts and financial reports.'],
            'finance_officer' => ['Finance Officer', 'Handles disbursements, collections and reconciliations.'],
            'ops_manager' => ['Operations Manager', 'Leads the operations center.'],
            'ops_supervisor' => ['Operations Supervisor', 'Supervises an operations shift team.'],
            'ops_officer' => ['Operations Officer', 'Executes daily operations tasks.'],
            'sales_manager' => ['Sales Manager', 'Leads sales and marketing.'],
            'sales_exec' => ['Sales Executive', 'Manages client accounts and new business.'],
            'admin_manager' => ['Administrative Manager', 'Leads office administration and facilities.'],
            'admin_officer' => ['Administrative Officer', 'Handles office administration and procurement.'],
            'cs_manager' => ['Customer Support Manager', 'Leads the customer support team.'],
            'csr' => ['Customer Support Representative', 'Handles customer inquiries and tickets.'],
        ] as $key => [$title, $description]) {
            $this->titles[$key] = $titleService->createJobTitle(['job_title' => $title, 'job_description' => $description]);
        }

        $statusService = app(EmploymentStatusService::class);

        foreach (['Regular', 'Probationary', 'Contractual', 'Temporary', 'Part-Time', 'Resigned', 'Retired', 'Terminated'] as $name) {
            $this->statuses[Str::snake(str_replace('-', '', $name))] = $statusService->create(['name' => $name]);
        }
    }

    // ------------------------------------------------------------------
    // Shifts, weekly schedules, holidays
    // ------------------------------------------------------------------

    private function timeConfiguration(): void
    {
        $shiftService = app(ShiftService::class);

        foreach ([
            'regular' => ['Regular Day', 'REG-DAY', '08:00', '17:00', '12:00', '13:00', 8],
            'early' => ['Early Shift', 'EARLY', '06:00', '15:00', '10:00', '11:00', 8],
            'mid' => ['Mid Shift', 'MID', '09:00', '18:00', '13:00', '14:00', 8],
            'late' => ['Late Shift', 'LATE', '13:00', '22:00', '17:00', '18:00', 8],
            'half' => ['Half Day (Morning)', 'HALF-AM', '08:00', '12:00', null, null, 4],
        ] as $key => [$name, $code, $start, $end, $breakStart, $breakEnd, $hours]) {
            $this->shifts[$key] = $shiftService->createShift([
                'name' => $name, 'code' => $code, 'description' => "{$name}: {$start}–{$end}",
                'start_time' => $start, 'end_time' => $end, 'break_start' => $breakStart, 'break_end' => $breakEnd,
                'required_hours' => $hours, 'is_overnight' => false, 'is_flexible' => false, 'is_active' => true,
            ]);
        }

        $scheduleService = app(WorkScheduleService::class);

        foreach ([
            'regular' => ['Regular Office (Mon–Fri)', 'REG-MF', 'regular', [1, 2, 3, 4, 5]],
            'early' => ['Early Shift (Mon–Fri)', 'EARLY-MF', 'early', [1, 2, 3, 4, 5]],
            'mid' => ['Mid Shift (Mon–Fri)', 'MID-MF', 'mid', [1, 2, 3, 4, 5]],
            'late' => ['Late Shift (Tue–Sat)', 'LATE-TS', 'late', [2, 3, 4, 5, 6]],
            'part_time' => ['Part-Time Mornings (Mon–Fri)', 'PT-AM', 'half', [1, 2, 3, 4, 5]],
        ] as $key => [$name, $code, $shift, $workingDays]) {
            $this->schedules[$key] = $scheduleService->createSchedule([
                'name' => $name, 'code' => $code, 'description' => "Acme {$name}", 'status' => 'active',
                'days' => collect(range(0, 6))->map(fn ($day) => [
                    'day_of_week' => $day,
                    'shift_id' => in_array($day, $workingDays, true) ? $this->shifts[$shift]->id : null,
                    'is_working_day' => in_array($day, $workingDays, true),
                ])->all(),
            ]);
        }

        $holidayService = app(HolidayService::class);

        foreach ([
            ["New Year's Day", 'NEW-YEAR', '2026-01-01', 'regular', true],
            ['Labor Day', 'LABOR-DAY', '2026-05-01', 'regular', true],
            ['Acme Foundation Day', 'ACME-FOUNDATION', '2026-10-09', 'company', false],
            ['Christmas Day', 'CHRISTMAS', '2026-12-25', 'regular', true],
            ["New Year's Eve", 'NYE', '2026-12-31', 'special_non_working', true],
        ] as [$name, $code, $date, $type, $recurring]) {
            $holidayService->createHoliday([
                'name' => $name, 'code' => $code, 'date' => $date, 'type' => $type,
                'is_paid' => true, 'is_working_day' => false, 'is_recurring' => $recurring, 'status' => 'active',
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Employees (created in their state at hire; movements bring them current)
    // ------------------------------------------------------------------

    /**
     * key => [first, middle, last, sex, birthday, marital, dept, title, status, location, supervisor, joined, schedule, mobile suffix]
     */
    private function roster(): array
    {
        return [
            'ceo' => ['Victoria', 'Elena', 'Santos', 'female', '1972-04-18', 'married', 'exec', 'ceo', 'regular', 'main', null, '2015-03-02', 'regular'],
            'coo' => ['Ramon', 'Jose', 'Villanueva', 'male', '1975-09-03', 'married', 'exec', 'coo', 'regular', 'main', 'ceo', '2016-06-01', 'regular'],
            'hrm' => ['Patricia', 'Anne', 'Mendoza', 'female', '1984-11-22', 'married', 'hr', 'hr_manager', 'regular', 'main', 'ceo', '2018-02-05', 'regular'],
            'hro' => ['Carlo', 'Miguel', 'Reyes', 'male', '1994-02-14', 'single', 'hr', 'hr_assistant', 'regular', 'main', 'hrm', '2021-01-11', 'regular'],
            'hrs' => ['Jasmine', 'Kate', 'Torres', 'female', '1992-07-30', 'single', 'hr', 'hr_specialist', 'regular', 'main', 'hrm', '2020-09-14', 'regular'],
            'hra' => ['Miguel', 'Antonio', 'Ramos', 'male', '2001-05-09', 'single', 'hr', 'hr_assistant', 'probationary', 'main', 'hrm', '2026-08-03', 'regular'],
            'itm' => ['Daniel', 'Joseph', 'Cruz', 'male', '1983-01-27', 'married', 'it', 'it_manager', 'regular', 'main', 'ceo', '2017-05-15', 'regular'],
            'sdev' => ['Angela', 'Marie', 'Bautista', 'female', '1993-10-05', 'single', 'it', 'junior_dev', 'regular', 'main', 'itm', '2019-04-01', 'regular'],
            'dev1' => ['Kevin', 'Paul', 'Lim', 'male', '1996-03-12', 'single', 'it', 'software_dev', 'regular', 'remote', 'sdev', '2021-08-02', 'mid'],
            'dev2' => ['Beatriz', 'Ann', 'Gonzales', 'female', '1997-12-01', 'single', 'it', 'software_dev', 'regular', 'main', 'sdev', '2022-02-07', 'regular'],
            'jdev' => ['Paolo', 'Luis', 'Navarro', 'male', '2002-06-21', 'single', 'it', 'junior_dev', 'probationary', 'main', 'sdev', '2026-09-14', 'regular'],
            'sysad' => ['Ronald', 'Francis', 'Aquino', 'male', '1988-08-08', 'married', 'it', 'sysadmin', 'regular', 'main', 'itm', '2016-11-07', 'early'],
            'itsup' => ['Lea', 'Grace', 'Fernandez', 'female', '1999-04-16', 'single', 'it', 'it_support', 'probationary', 'main', 'itm', '2025-10-06', 'regular'],
            'finm' => ['Teresa', 'Joy', 'Castillo', 'female', '1980-02-02', 'married', 'fin', 'finance_manager', 'regular', 'main', 'ceo', '2017-08-01', 'regular'],
            'acct1' => ['Mark', 'Anthony', 'Dizon', 'male', '1991-06-30', 'married', 'fin', 'accountant', 'regular', 'main', 'finm', '2019-07-01', 'regular'],
            'fino' => ['Grace', 'Ann', 'Pascual', 'female', '1990-12-12', 'married', 'fin', 'accountant', 'regular', 'main', 'finm', '2018-03-19', 'regular'],
            'acct2' => ['Ivy', 'Rose', 'Salazar', 'female', '1998-09-09', 'single', 'fin', 'accountant', 'contractual', 'main', 'finm', '2026-03-02', 'regular'],
            'opsm' => ['Antonio', 'Luis', 'Garcia', 'male', '1979-05-25', 'married', 'ops', 'ops_manager', 'regular', 'ops', 'coo', '2016-08-15', 'regular'],
            'opss1' => ['Liza', 'Mae', 'Ramirez', 'female', '1989-03-03', 'married', 'ops', 'ops_supervisor', 'regular', 'ops', 'opsm', '2018-10-01', 'early'],
            'opss2' => ['Jerome', 'Paul', 'Valdez', 'male', '1987-11-11', 'married', 'ops', 'ops_supervisor', 'regular', 'ops', 'opsm', '2019-02-04', 'late'],
            'opo1' => ['Nina', 'Louise', 'Flores', 'female', '1995-08-19', 'single', 'ops', 'ops_officer', 'regular', 'ops', 'opss1', '2020-01-13', 'early'],
            'opo2' => ['Joshua', 'Mark', 'Tan', 'male', '1997-01-25', 'single', 'ops', 'ops_officer', 'regular', 'ops', 'opss1', '2021-06-07', 'early'],
            'opo3' => ['Clarisse', 'Joy', 'Ong', 'female', '1996-05-05', 'single', 'ops', 'ops_officer', 'regular', 'ops', 'opss2', '2022-09-05', 'late'],
            'opo4' => ['Benjamin', 'Cruz', 'Santos', 'male', '1985-12-03', 'married', 'ops', 'ops_officer', 'part_time', 'ops', 'opss2', '2025-06-02', 'part_time'],
            'opo5' => ['Faith', 'Anne', 'Cortez', 'female', '2000-10-10', 'single', 'ops', 'ops_officer', 'temporary', 'ops', 'opss1', '2026-06-15', 'early'],
            'salm' => ['Richard', 'Allen', 'Lopez', 'male', '1982-07-07', 'married', 'sales', 'sales_manager', 'regular', 'north', 'coo', '2017-01-09', 'regular'],
            'sal1' => ['Camille', 'Joy', 'Rivera', 'female', '1994-04-04', 'single', 'sales', 'sales_exec', 'regular', 'north', 'salm', '2020-03-02', 'regular'],
            'sal2' => ['Lorenzo', 'Diego', 'Velasco', 'male', '1993-12-24', 'married', 'sales', 'sales_exec', 'regular', 'south', 'salm', '2019-09-16', 'regular'],
            'sal3' => ['Hannah', 'Grace', 'Medina', 'female', '2000-03-01', 'single', 'sales', 'sales_exec', 'probationary', 'north', 'salm', '2026-07-01', 'regular'],
            'csm' => ['Monica', 'Clare', 'De Leon', 'female', '1986-09-21', 'married', 'cs', 'cs_manager', 'regular', 'ops', 'coo', '2018-06-04', 'mid'],
            'csr1' => ['Andrea', 'Nicole', 'Morales', 'female', '1995-11-30', 'single', 'ops', 'ops_officer', 'regular', 'ops', 'opss1', '2022-03-07', 'mid'],
            'csr2' => ['Luis', 'Rafael', 'Mercado', 'male', '1998-07-14', 'single', 'cs', 'csr', 'regular', 'ops', 'csm', '2023-04-10', 'mid'],
            'csr3' => ['Sofia', 'Isabel', 'Herrera', 'female', '1999-01-08', 'single', 'cs', 'csr', 'regular', 'ops', 'csm', '2024-01-15', 'mid'],
            'csr4' => ['Gabriel', 'Jose', 'Aguilar', 'male', '1996-10-22', 'married', 'cs', 'csr', 'regular', 'north', 'csm', '2022-11-07', 'mid'],
            'admm' => ['Cynthia', 'Rose', 'Robles', 'female', '1981-03-15', 'married', 'admin', 'admin_manager', 'regular', 'main', 'coo', '2017-10-02', 'regular'],
            'admo' => ['Jonas', 'Michael', 'Pineda', 'male', '1992-08-27', 'married', 'admin', 'admin_officer', 'regular', 'main', 'admm', '2021-03-01', 'regular'],
            'sep1' => ['Marvin', 'James', 'Ocampo', 'male', '1991-05-17', 'single', 'sales', 'sales_exec', 'regular', 'south', 'salm', '2020-06-01', null],
            'sep2' => ['Ernesto', 'Luis', 'Domingo', 'male', '1961-02-20', 'married', 'admin', 'admin_officer', 'regular', 'main', 'admm', '2015-04-06', null],
            'sep3' => ['Dexter', 'Allan', 'Quiambao', 'male', '1990-09-12', 'single', 'ops', 'ops_officer', 'regular', 'ops', 'opss2', '2023-05-02', null],
        ];
    }

    private function people(): void
    {
        $service = app(EmployeeService::class);
        $streets = ['Mabini Street', 'Rizal Avenue', 'Bonifacio Drive', 'Luna Street', 'Del Pilar Road', 'Aguinaldo Street', 'Magsaysay Avenue', 'Quezon Boulevard'];
        $cities = [['Pasig City', '1600'], ['Quezon City', '1100'], ['Taguig City', '1630'], ['Makati City', '1200'], ['Mandaluyong City', '1550'], ['Muntinlupa City', '1770']];
        $filipino = DB::table('nationalities')->where('name', 'Filipino')->value('id');
        $n = 0;

        foreach ($this->roster() as $key => [$first, $middle, $last, $sex, $birthday, $marital, $dept, $title, $status, $location, $supervisor, $joined]) {
            $n++;
            [$city, $zip] = $cities[$n % count($cities)];
            $slug = Str::slug("{$first}.{$last}", '.');

            $this->employees[$key] = $service->createEmployee([
                'employee_number' => sprintf('ACME-%04d', $n),
                'emp_first_name' => $first,
                'emp_middle_name' => $middle,
                'emp_last_name' => $last,
                'emp_sex' => $sex,
                'emp_birthday' => $birthday,
                'emp_marital_status' => $marital,
                'emp_nationality_id' => $filipino,
                'street1' => sprintf('%d %s', 10 + $n * 7, $streets[$n % count($streets)]),
                'street2' => 'Barangay '.(100 + $n),
                'city' => $city,
                'province' => 'Metro Manila',
                'zip_code' => $zip,
                'country_id' => 'Philippines',
                'mobile_no' => sprintf('0917-555-%04d', 1000 + $n),
                'work_no' => sprintf('+63 2 8555 %04d', 1000 + $n),
                'work_email' => "{$slug}@acmedigital.example",
                'other_email' => "{$slug}.personal@mail.example",
                'joined_date' => $joined,
                'job_title_id' => $this->titles[$title]->id,
                'department_id' => $this->departments[$dept]->id,
                'location_id' => $this->locations[$location]->id,
                'employment_status_id' => $this->statuses[$status]->id,
                'status' => 'active',
                'supervisor_id' => $supervisor ? $this->employees[$supervisor]->id : null,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Login accounts (each linked to an employee)
    // ------------------------------------------------------------------

    private function accounts(): void
    {
        $service = app(UserService::class);

        foreach ($this->accountPlan() as $key => [$username, $roles]) {
            $this->users[$key] = $service->createUser([
                'username' => $username,
                'password' => $this->password,
                'employee_id' => $this->employees[$key]->id,
                'roles' => $roles,
            ]);
        }
    }

    /**
     * employee key => [username, roles]
     */
    private function accountPlan(): array
    {
        return [
            'hrm' => ['patricia.mendoza', ['hr_director']],
            'hro' => ['carlo.reyes', ['hr_staff']],
            'ceo' => ['victoria.santos', ['supervisor']],
            'coo' => ['ramon.villanueva', ['supervisor']],
            'itm' => ['daniel.cruz', ['supervisor']],
            'sdev' => ['angela.bautista', ['supervisor']],
            'finm' => ['teresa.castillo', ['supervisor']],
            'opsm' => ['antonio.garcia', ['supervisor']],
            'opss1' => ['liza.ramirez', ['supervisor']],
            'opss2' => ['jerome.valdez', ['supervisor']],
            'salm' => ['richard.lopez', ['supervisor']],
            'csm' => ['monica.deleon', ['supervisor']],
            'admm' => ['cynthia.robles', ['supervisor']],
            'opo1' => ['nina.flores', ['employee']],
            'opo2' => ['joshua.tan', ['employee']],
            'csr1' => ['andrea.morales', ['employee']],
            'dev1' => ['kevin.lim', ['employee']],
            'dev2' => ['bea.gonzales', ['employee']],
        ];
    }

    // ------------------------------------------------------------------
    // Employee movements (hiring baseline for everyone, then career events)
    // ------------------------------------------------------------------

    private function movements(): void
    {
        $plan = [];

        foreach ($this->roster() as $key => $row) {
            $plan[$key] = [['hiring', $row[11], [], 'New hire.']];
        }

        $plan['sdev'][] = ['promotion', '2021-05-01', ['job_title_id' => $this->titles['software_dev']->id], 'Promoted after two years of strong delivery.'];
        $plan['sdev'][] = ['promotion', '2024-01-15', ['job_title_id' => $this->titles['senior_dev']->id], 'Promoted to Senior and appointed development team lead.'];
        $plan['hro'][] = ['promotion', '2023-07-01', ['job_title_id' => $this->titles['hr_officer']->id], 'Promoted to HR Officer.'];
        $plan['csr1'][] = ['department_transfer', '2025-02-01', [
            'department_id' => $this->departments['cs']->id,
            'job_title_id' => $this->titles['csr']->id,
            'supervisor_id' => $this->employees['csm']->id,
        ], 'Transferred to Customer Support to strengthen the support team.'];
        $plan['csr4'][] = ['transfer', '2026-04-01', ['location_id' => $this->locations['south']->id], 'Location transfer to the South Branch support desk.'];
        $plan['sysad'][] = ['reassignment', '2026-02-01', ['location_id' => $this->locations['ops']->id], 'Reassigned on-site to the Operations Center data room.'];
        $plan['fino'][] = ['job_title_change', '2025-09-01', ['job_title_id' => $this->titles['finance_officer']->id], 'Retitled to Finance Officer after finance team restructuring.'];
        $plan['itsup'][] = ['employment_status_change', '2026-04-06', ['employment_status_id' => $this->statuses['regular']->id], 'Regularized after completing probation.'];
        $plan['sep1'][] = ['resignation', '2026-06-30', ['employment_status_id' => $this->statuses['resigned']->id], 'Resigned to pursue opportunities abroad.'];
        $plan['sep2'][] = ['retirement', '2026-03-31', ['employment_status_id' => $this->statuses['retired']->id], 'Compulsory retirement at age 65.'];
        $plan['sep3'][] = ['termination', '2025-11-14', ['employment_status_id' => $this->statuses['terminated']->id], 'Terminated for repeated violations of the attendance policy.'];

        $service = app(EmployeeMovementService::class);
        $types = EmployeeMovementType::pluck('id', 'code');

        foreach ($plan as $key => $events) {
            // HR records employment changes; nobody records their own.
            $actor = $key === 'hrm' ? $this->users['hro'] : $this->users['hrm'];

            foreach ($events as [$code, $effective, $changes, $reason]) {
                Carbon::setTestNow(Carbon::parse("{$effective} 10:00:00"));

                $data = [
                    'employee_id' => $this->employees[$key]->id,
                    'movement_type_id' => $types[$code],
                    'effective_date' => $effective,
                    'reason' => $reason,
                    'reference_number' => 'MOV-'.str_replace('-', '', $effective).'-'.$this->employees[$key]->employee_number,
                    'changed_fields' => array_keys($changes),
                ];

                foreach ($changes as $field => $value) {
                    $data["to_{$field}"] = $value;
                }

                $service->createMovement($data, $actor);
            }
        }

        Carbon::setTestNow();

        foreach ($this->employees as $key => $employee) {
            $this->employees[$key] = $employee->fresh();
        }
    }

    // ------------------------------------------------------------------
    // Schedule assignments (effective-dated)
    // ------------------------------------------------------------------

    private function scheduleAssignments(): void
    {
        $service = app(EmployeeWorkScheduleService::class);

        foreach ($this->roster() as $key => $row) {
            $schedule = $row[12];

            if ($schedule === null) {
                continue; // separated before 2026 schedules were introduced or no longer employed
            }

            $from = max($row[11], '2026-01-05');

            // Sofia moved from the mid to the late shift in August.
            $initial = $key === 'csr3' ? 'mid' : $schedule;

            $service->assignSchedules([
                'employee_ids' => [$this->employees[$key]->id],
                'work_schedule_id' => $this->schedules[$initial]->id,
                'effective_from' => $from,
                'is_primary' => true,
                'remarks' => 'Initial schedule assignment',
            ]);
        }

        $service->assignSchedules([
            'employee_ids' => [$this->employees['csr3']->id],
            'work_schedule_id' => $this->schedules['late']->id,
            'effective_from' => '2026-08-03',
            'is_primary' => true,
            'remarks' => 'Moved to the late shift to extend support coverage.',
        ]);
    }

    // ------------------------------------------------------------------
    // Leave types, policy, rules, approval workflow
    // ------------------------------------------------------------------

    private function leaveConfiguration(): void
    {
        $typeService = app(LeaveTypeService::class);

        $existing = LeaveType::pluck('id', 'code');

        foreach ([
            'VL' => ['Vacation Leave', 'Paid time off for rest and personal travel.'],
            'SL' => ['Sick Leave', 'Paid leave when ill or for medical appointments.'],
            'EL' => ['Emergency Leave', 'Short-notice leave for urgent personal or family matters.'],
            'PL' => ['Personal Leave', 'Leave for personal errands and obligations.'],
            'BL' => ['Bereavement Leave', 'Leave following the death of an immediate family member.'],
            'ML' => ['Maternity Leave', 'Leave for childbirth and recovery.'],
            'PTL' => ['Paternity Leave', 'Leave for fathers after the birth of a child.'],
        ] as $code => [$name, $description]) {
            $this->leaveTypes[$code] = isset($existing[$code])
                ? LeaveType::find($existing[$code])
                : $typeService->createType(['name' => $name, 'code' => $code, 'description' => $description, 'is_active' => true]);
        }

        // An older active policy auto-approves Vacation Leave; with two active
        // policies the rule lookup would be ambiguous, so it is archived.
        $policyService = app(LeavePolicyService::class);

        foreach (LeavePolicy::where('is_active', true)->get() as $old) {
            $policyService->archivePolicy($old);
        }

        $policy = $policyService->createPolicy([
            'name' => 'Acme Leave Policy 2026',
            'description' => 'Company-wide leave entitlements and rules for calendar year 2026.',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'is_active' => true,
        ]);

        $ruleService = app(LeavePolicyRuleService::class);

        foreach ([
            // code => [accrual, rate, max, carry, min months, half day, attachment]
            'VL' => ['annually', 15, 30, 5, 6, true, false],
            'SL' => ['annually', 10, 20, 5, 0, true, false],
            'EL' => ['fixed', 3, 3, 0, 0, true, false],
            'PL' => ['annually', 5, 5, 0, 3, true, false],
            'BL' => ['fixed', 5, 5, 0, 0, false, false],
            'ML' => ['none', 105, 105, 0, 0, false, true],
            'PTL' => ['none', 7, 7, 0, 0, false, true],
        ] as $code => [$accrual, $rate, $max, $carry, $minMonths, $halfDay, $attachment]) {
            $ruleService->createRule([
                'leave_policy_id' => $policy->id,
                'leave_type_id' => $this->leaveTypes[$code]->id,
                'accrual_method' => $accrual,
                'accrual_rate' => $rate,
                'grant_frequency' => $accrual === 'annually' ? 'annually' : 'none',
                'maximum_balance' => $max,
                'carry_forward_limit' => $carry,
                'minimum_service_months' => $minMonths,
                'waiting_period' => 0,
                'allow_negative' => false,
                'requires_approval' => true,
                'requires_attachment' => $attachment,
                'allows_half_day' => $halfDay,
                'allows_hourly' => false,
                'expires' => $code === 'VL',
                'is_active' => true,
            ]);
        }

        app(LeaveApprovalWorkflowService::class)->createWorkflow([
            'name' => 'Acme Standard Leave Approval',
            'description' => 'Immediate supervisor, then their supervisor (skipped when there is none).',
            'leave_policy_id' => $policy->id,
            'is_active' => true,
            'steps' => [
                ['approver_type' => 'immediate_supervisor', 'is_required' => true],
                ['approver_type' => 'higher_supervisor', 'is_required' => false],
            ],
        ]);
    }

    private function leaveBalances(): void
    {
        $service = app(LeaveBalanceService::class);
        $entitlements = ['VL' => 15, 'SL' => 10, 'EL' => 3, 'PL' => 5, 'BL' => 5];

        foreach ($this->employees as $employee) {
            if ($employee->status !== 'active') {
                continue;
            }

            $grants = $entitlements + ($employee->emp_sex === 'female' ? ['ML' => 105] : ['PTL' => 7]);

            foreach ($grants as $code => $days) {
                $service->createBalance([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $this->leaveTypes[$code]->id,
                    'balance' => $days,
                    'used' => 0,
                    'pending' => 0,
                    'as_of_date' => '2026-01-01',
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Leave applications through the real filing/approval services
    // ------------------------------------------------------------------

    private function leaveScenarios(): void
    {
        // [employee, type, dates (date => full|half), reason, filed at, actions [[approve|reject|return|cancel, at, remarks]]]
        $scenarios = [
            ['sdev', 'VL', ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10'], 'Family vacation in Palawan.', '2026-06-15 09:20', [['approve', '2026-06-16 10:05', 'Enjoy your trip!'], ['approve', '2026-06-17 08:40', 'Approved.']]],
            ['opo2', 'SL', ['2026-07-20'], 'Fever and flu.', '2026-07-20 07:10', [['approve', '2026-07-20 11:30', 'Get well soon.'], ['approve', '2026-07-21 09:00', null]]],
            ['opo1', 'VL', ['2026-08-17', '2026-08-18', '2026-08-19'], 'Out-of-town family event.', '2026-07-28 13:45', [['approve', '2026-07-29 08:15', null], ['approve', '2026-07-30 09:30', 'Approved, coverage arranged.']]],
            ['acct1', 'BL', ['2026-08-24', '2026-08-25', '2026-08-26'], 'Death of grandmother.', '2026-08-23 19:02', [['approve', '2026-08-24 08:10', 'Our condolences.'], ['approve', '2026-08-24 09:45', null]]],
            ['sal1', 'EL', ['2026-09-08'], 'Child hospitalized.', '2026-09-08 06:55', [['approve', '2026-09-08 09:00', null], ['approve', '2026-09-08 14:20', null]]],
            ['opo1', 'SL', ['2026-09-15'], 'Dental procedure.', '2026-09-14 16:30', [['approve', '2026-09-14 17:05', null], ['approve', '2026-09-15 08:30', null]]],
            ['csr2', 'SL', ['2026-09-22' => 'half'], 'Medical check-up in the morning.', '2026-09-18 10:00', [['approve', '2026-09-18 15:00', null], ['approve', '2026-09-19 10:10', null]]],
            ['sal3', 'EL', ['2026-09-25'], 'Typhoon damage at home.', '2026-09-25 06:40', [['approve', '2026-09-25 08:00', null], ['approve', '2026-09-25 11:00', null]]],
            ['opss1', 'PL', ['2026-09-29'], 'Personal errands (bank and government office).', '2026-09-21 09:00', [['approve', '2026-09-21 13:00', null], ['approve', '2026-09-22 09:15', null]]],
            ['dev1', 'VL', ['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23'], 'Week-long vacation.', '2026-09-25 14:00', [['reject', '2026-09-26 10:30', 'Release week for the client portal; please choose other dates.']]],
            ['opo3', 'SL', ['2026-09-30', '2026-10-01'], 'Recovering from food poisoning.', '2026-09-29 18:20', [['return', '2026-09-30 15:00', 'Please attach a medical certificate for sick leave longer than one day and resubmit.']]],
            ['dev2', 'PL', ['2026-10-16'], 'Moving apartments.', '2026-09-28 11:00', [['cancel', '2026-09-30 09:45', null]]],
            ['csr1', 'VL', ['2026-10-26', '2026-10-27'], 'Long weekend with family.', '2026-09-30 10:15', [['approve', '2026-10-01 09:00', 'Approved on my end.']]],
            ['opo2', 'VL', ['2026-10-12', '2026-10-13', '2026-10-14'], 'Attending a friend\'s wedding in Cebu.', '2026-10-01 08:30', []],
            ['itm', 'VL', ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06'], 'Annual family holiday.', '2026-10-02 16:00', []],
        ];

        $applications = app(LeaveApplicationService::class);
        $approvals = app(LeaveApprovalService::class);

        foreach ($scenarios as [$key, $code, $dates, $reason, $filedAt, $actions]) {
            Carbon::setTestNow(Carbon::parse($filedAt));

            $dateRows = collect($dates)->map(function ($value, $index) {
                [$date, $duration] = is_string($index) ? [$index, $value === 'half' ? 'half_day' : 'full_day'] : [$value, 'full_day'];

                return ['leave_date' => $date, 'duration_type' => $duration];
            })->values()->all();

            $application = $applications->createApplication(
                $this->employees[$key]->id,
                ['leave_type_id' => $this->leaveTypes[$code]->id, 'reason' => $reason],
                $dateRows
            );

            foreach ($actions as [$action, $at, $remarks]) {
                Carbon::setTestNow(Carbon::parse($at));
                $application->refresh();

                if ($action === 'cancel') {
                    $actor = $this->users[$key];
                    Gate::forUser($actor)->authorize('cancel', $application);
                    $applications->cancelApplication($application, $actor);

                    continue;
                }

                $approverId = (int) $application->currentApproval()->approver_id;
                $approver = User::where('employee_id', $approverId)->firstOrFail();
                Gate::forUser($approver)->authorize($action, $application);

                match ($action) {
                    'approve' => $approvals->approve($application, $approverId, $remarks),
                    'reject' => $approvals->reject($application, $approverId, $remarks),
                    'return' => $approvals->return($application, $approverId, $remarks),
                };
            }
        }

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // Raw attendance punches (device logs) for four weeks
    // ------------------------------------------------------------------

    private function attendance(): void
    {
        $schedules = app(EmployeeWorkScheduleService::class);
        $holidayService = app(HolidayService::class);
        $from = Carbon::parse(self::ATTENDANCE_FROM);
        $to = Carbon::parse(self::ATTENDANCE_TO);

        $active = collect($this->employees)->filter(fn ($e) => $e->status === 'active');
        $assignments = $schedules->assignmentsQuery($active->pluck('id'), $from->toDateString(), $to->toDateString())->get()->groupBy('employee_id');
        $holidays = $holidayService->getForRange($from, $to);

        $onLeave = LeaveApplicationDate::query()
            ->whereHas('application', fn ($q) => $q->where('status', LeaveApplication::STATUS_APPROVED))
            ->get()
            ->map(fn ($d) => $d->application->employee_id.'|'.$d->leave_date->toDateString())
            ->flip();

        $devices = [
            'main' => ['Main Lobby Terminal', 'ZK-MAIN-0101'],
            'north' => ['North Branch Terminal', 'ZK-NB-0201'],
            'south' => ['South Branch Terminal', 'ZK-SB-0301'],
            'ops' => ['Operations Center Gate', 'ZK-OPS-0401'],
            'remote' => ['Mobile Clock-in', 'MOBILE-APP'],
        ];
        $locationKeys = collect($this->locations)->mapWithKeys(fn ($l, $k) => [$l->id => $k]);

        foreach ($active->values() as $index => $employee) {
            $biometricId = (string) (1001 + $index);
            EmployeeBiometricId::create(['employee_id' => $employee->id, 'biometric_id' => $biometricId]);
            [$device, $serial] = $devices[$locationKeys[$employee->location_id] ?? 'main'];

            foreach (CarbonPeriod::create($from, $to) as $date) {
                $day = $schedules->resolveDay($assignments->get($employee->id, collect()), $date, $holidayService->matchDate($holidays, $date));

                if (! $day['is_working_day'] || isset($onLeave[$employee->id.'|'.$date->toDateString()])) {
                    continue;
                }

                if (mt_rand(1, 100) <= 2) {
                    continue; // unrecorded absence
                }

                $start = Carbon::parse($date->toDateString().' '.$day['shift']['start_time']);
                $end = Carbon::parse($date->toDateString().' '.$day['shift']['end_time']);

                $in = mt_rand(1, 100) <= 12 ? $start->copy()->addMinutes(mt_rand(6, 35)) : $start->copy()->subMinutes(mt_rand(0, 20))->addMinutes(mt_rand(0, 4));
                $out = mt_rand(1, 100) <= 6 ? $end->copy()->subMinutes(mt_rand(10, 45)) : $end->copy()->addMinutes(mt_rand(0, 25));

                $punches = [['check in', $in->addSeconds(mt_rand(0, 59))]];

                if ((float) $day['shift']['required_hours'] >= 8 && mt_rand(1, 100) <= 35) {
                    $breakOut = $start->copy()->addHours(4)->addMinutes(mt_rand(0, 15));
                    $punches[] = ['break out', $breakOut->copy()->addSeconds(mt_rand(0, 59))];
                    $punches[] = ['break in', $breakOut->copy()->addMinutes(mt_rand(52, 62))->addSeconds(mt_rand(0, 59))];
                }

                if (mt_rand(1, 100) > 3) {
                    $punches[] = ['check out', $out->addSeconds(mt_rand(0, 59))];
                }

                foreach ($punches as [$direction, $at]) {
                    EmployeeAttendanceLog::create([
                        'employee_id' => $biometricId,
                        'auth_date_time' => $at->toDateTimeString(),
                        'auth_date' => $at->toDateString(),
                        'auth_time' => $at->toTimeString(),
                        'direction' => $direction,
                        'device_name' => $device,
                        'device_sn' => $serial,
                        'person_name' => strtoupper($employee->emp_last_name.', '.$employee->emp_first_name),
                        'card_no' => 'C'.$biometricId,
                    ]);
                }
            }
        }

        // A visitor card that was never registered to an employee.
        foreach (['2026-09-16 09:12:44', '2026-09-16 15:48:10'] as $i => $at) {
            $time = Carbon::parse($at);
            EmployeeAttendanceLog::create([
                'employee_id' => '9001',
                'auth_date_time' => $time->toDateTimeString(),
                'auth_date' => $time->toDateString(),
                'auth_time' => $time->toTimeString(),
                'direction' => $i === 0 ? 'check in' : 'check out',
                'device_name' => 'Main Lobby Terminal',
                'device_sn' => 'ZK-MAIN-0101',
                'person_name' => 'VISITOR',
                'card_no' => 'V9001',
            ]);
        }
    }
}
