<?php

/**
 * Worker process for OnboardingConcurrencyTest. Scratch MySQL database only:
 * DB_DATABASE must end with "_concurrency_test".
 *
 *   setup                                 create + migrate + seed roles; people and one template
 *   start <username> <start>              HR starts onboarding for that person (ok / denied)
 *   ready <username> <all|missing>        fixture: a case with every required task done (or one left), prints its id
 *   task <onboardingId> <title>           prints the task id
 *   complete-task <taskId> <username> <start>   (ok / denied)
 *   complete <onboardingId> <start>       HR completes the onboarding (ok / denied)
 *   timestamp-stability                   lifecycle timestamps vs unrelated edits
 *   schema-onupdate                       onboarding columns with ON UPDATE CURRENT_TIMESTAMP
 *   report                                cases, tasks and events
 *   teardown                              drop the scratch database
 */

use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\OnboardingEvent;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingTaskService;
use App\Services\Onboarding\OnboardingTemplateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$database = (string) getenv('DB_DATABASE');
if (getenv('DB_CONNECTION') !== 'mysql' || ! preg_match('/^[A-Za-z0-9_]+_concurrency_test$/', $database)) {
    fwrite(STDERR, "Refusing to run: DB_CONNECTION must be mysql and DB_DATABASE must end with _concurrency_test.\n");
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$mode = array_shift($args);

$server = function (string $sql) use ($database) {
    config(['database.connections.mysql.database' => null]);
    DB::purge('mysql');
    DB::connection('mysql')->statement($sql);
    config(['database.connections.mysql.database' => $database]);
    DB::purge('mysql');
};
$waitUntil = function (float $start) {
    while (microtime(true) < $start) {
        usleep(1000);
    }
};
$user = fn (string $username) => User::where('username', $username)->firstOrFail();
$employee = fn (string $username) => Employee::findOrFail($user($username)->employee_id);
$attempt = function (callable $action) {
    try {
        $action();
        echo 'ok';
    } catch (ValidationException $e) {
        echo 'denied: '.collect($e->errors())->flatten()->first();
    }
};
$startCase = fn (string $username) => app(OnboardingService::class)->start($employee($username), [
    'onboarding_template_id' => OnboardingTemplate::value('id'),
    'start_date' => date('Y-m-d', strtotime('+3 days')),
], $user('hana'));

switch ($mode) {
    case 'setup':
        $server("CREATE DATABASE IF NOT EXISTS `{$database}`");
        Artisan::call('migrate:fresh', ['--force' => true]);
        (new RolesAndPermissionsSeeder)->run();

        $person = function (string $username, string $role, ?int $supervisorId = null) {
            $employee = Employee::create(['employee_number' => strtoupper($username), 'emp_first_name' => ucfirst($username), 'emp_last_name' => 'Test', 'emp_sex' => 'other', 'status' => 'active', 'supervisor_id' => $supervisorId]);
            User::create(['username' => $username, 'password' => bcrypt('x'), 'status' => 'active', 'employee_id' => $employee->id])->assignRole($role);

            return $employee->id;
        };
        $person('hana', 'hr_staff');
        $person('mona', 'hr_manager');
        $sam = $person('sam', 'supervisor');
        foreach (['nina', 'ollie', 'pia', 'quinn', 'rex', 'sue'] as $name) {
            $person($name, 'employee', $sam);
        }

        $row = fn (array $over) => $over + ['description' => null, 'category' => 'first_day', 'assignee_type' => 'hr', 'assignee_employee_id' => null,
            'due_relative_to' => 'start_date', 'due_offset_days' => 0, 'is_required' => true, 'employee_visible' => true,
            'requires_verification' => false, 'required_document_type_id' => null, 'is_active' => true];
        app(OnboardingTemplateService::class)->createTemplate(['name' => 'Standard', 'description' => null, 'employment_status_id' => null, 'is_active' => true, 'tasks' => [
            $row(['title' => 'Prepare workstation']),
            $row(['title' => 'Meet supervisor', 'assignee_type' => 'supervisor']),
            $row(['title' => 'Sign handbook', 'assignee_type' => 'employee', 'due_offset_days' => 1]),
            $row(['title' => 'Welcome lunch', 'is_required' => false, 'due_offset_days' => 3]),
        ]], $user('mona'));
        echo json_encode(['ok' => true]);
        break;

    case 'start':
        [$username, $start] = [$args[0], (float) $args[1]];
        $who = $employee($username);
        $data = ['onboarding_template_id' => OnboardingTemplate::value('id'), 'start_date' => date('Y-m-d', strtotime('+3 days'))];
        $waitUntil($start);
        $attempt(fn () => app(OnboardingService::class)->start($who, $data, $user('hana')));
        break;

    case 'ready':
        [$username, $variant] = [$args[0], $args[1]];
        $case = $startCase($username);
        $tasks = app(OnboardingTaskService::class);
        $tasks->complete($case->tasks()->where('title', 'Prepare workstation')->first(), [], $user('hana'));
        $tasks->complete($case->tasks()->where('title', 'Meet supervisor')->first(), [], $user('sam'));
        if ($variant === 'all') {
            $tasks->complete($case->tasks()->where('title', 'Sign handbook')->first(), [], $user($username));
        }
        echo $case->id;
        break;

    case 'task':
        echo OnboardingTask::where('onboarding_id', (int) $args[0])->where('title', $args[1])->value('id');
        break;

    case 'complete-task':
        [$id, $username, $start] = [(int) $args[0], $args[1], (float) $args[2]];
        $task = OnboardingTask::findOrFail($id);
        $actor = $user($username);
        $waitUntil($start);
        $attempt(fn () => app(OnboardingTaskService::class)->complete($task, ['remarks' => 'by '.getmypid()], $actor));
        break;

    case 'complete':
        [$id, $start] = [(int) $args[0], (float) $args[1]];
        $case = Onboarding::findOrFail($id);
        $waitUntil($start);
        $attempt(fn () => app(OnboardingService::class)->complete($case, $user('hana'), 'race'));
        break;

    case 'timestamp-stability':
        $done = $startCase('nina');
        $tasks = app(OnboardingTaskService::class);
        $tasks->start($done->tasks()->where('title', 'Prepare workstation')->first(), $user('hana'));
        $tasks->complete($done->tasks()->where('title', 'Prepare workstation')->first(), [], $user('hana'));
        $tasks->complete($done->tasks()->where('title', 'Meet supervisor')->first(), [], $user('sam'));
        $tasks->complete($done->tasks()->where('title', 'Sign handbook')->first(), [], $user('nina'));
        $tasks->skip($done->tasks()->where('title', 'Welcome lunch')->first(), 'n/a', $user('hana'));
        app(OnboardingService::class)->complete($done, $user('hana'));
        app(OnboardingService::class)->cancel($startCase('ollie'), 'x', $user('mona'));

        $columns = [
            'onboardings' => ['start_date', 'completed_at', 'cancelled_at'],
            'onboarding_tasks' => ['due_date', 'started_at', 'completed_at', 'verified_at', 'skipped_at', 'cancelled_at'],
            'onboarding_events' => ['occurred_at'],
        ];
        $read = fn () => collect($columns)->map(fn ($cols, $table) => DB::table($table)->orderBy('id')->get(['id', ...$cols])->toArray())->all();
        $before = $read();
        sleep(2);
        DB::table('onboardings')->update(['notes' => 'edited']);
        DB::table('onboarding_tasks')->update(['description' => 'edited']);
        DB::table('onboarding_events')->update(['remarks' => 'edited']);
        echo json_encode(['before' => $before, 'after' => $read()]);
        break;

    case 'schema-onupdate':
        $tables = ['onboarding_templates', 'onboarding_template_tasks', 'onboardings', 'onboarding_tasks', 'onboarding_events', 'onboarding_notes'];
        echo json_encode(collect(DB::select('select table_name as t, column_name as c from information_schema.columns where table_schema = database() and extra like ?', ['%on update%']))
            ->filter(fn ($r) => in_array($r->t, $tables, true))->map(fn ($r) => "{$r->t}.{$r->c}")->values());
        break;

    case 'report':
        echo json_encode([
            'onboardings' => Onboarding::orderBy('id')->get(['id', 'employee_id', 'status', 'completed_by'])->toArray(),
            'tasks' => OnboardingTask::orderBy('id')->get(['id', 'onboarding_id', 'onboarding_template_task_id', 'title', 'status', 'completed_by'])->toArray(),
            'events' => OnboardingEvent::orderBy('id')->get(['onboarding_id', 'onboarding_task_id', 'event'])->toArray(),
        ]);
        break;

    case 'teardown':
        $server("DROP DATABASE IF EXISTS `{$database}`");
        echo json_encode(['ok' => true]);
        break;

    default:
        fwrite(STDERR, "Unknown mode.\n");
        exit(2);
}
