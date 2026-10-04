<?php

/**
 * Worker process for EmployeeNumberConcurrencyTest. Runs against a scratch MySQL
 * database only: DB_DATABASE must end with "_concurrency_test".
 *
 *   php employee_number_worker.php setup               create the scratch DB and migrate it
 *   php employee_number_worker.php create <n> <start>  wait until <start> (unix time), then create n employees
 *   php employee_number_worker.php report              print issued numbers and the sequence counter
 *   php employee_number_worker.php teardown            drop the scratch DB
 */

use App\Models\Employee;
use App\Models\NumberSequence;
use App\Services\EmployeeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$database = (string) getenv('DB_DATABASE');
if (getenv('DB_CONNECTION') !== 'mysql' || ! preg_match('/^[A-Za-z0-9_]+_concurrency_test$/', $database)) {
    fwrite(STDERR, "Refusing to run: DB_CONNECTION must be mysql and DB_DATABASE must end with _concurrency_test.\n");
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? '';

// Server-level statements run on a connection that has no default database selected.
$server = function (string $sql) use ($database) {
    config(['database.connections.mysql.database' => null]);
    DB::purge('mysql');
    DB::connection('mysql')->statement($sql);
    config(['database.connections.mysql.database' => $database]);
    DB::purge('mysql');
};

switch ($mode) {
    case 'setup':
        $server("CREATE DATABASE IF NOT EXISTS `{$database}`");
        Artisan::call('migrate:fresh', ['--force' => true]);
        echo json_encode(['ok' => true]);
        break;

    case 'create':
        [$count, $startAt] = [(int) $argv[2], (float) $argv[3]];
        while (microtime(true) < $startAt) {
            usleep(1000);
        }
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = app(EmployeeService::class)->createEmployee([
                'employee_number' => null,
                'emp_first_name' => 'Worker'.getmypid(),
                'emp_last_name' => 'Hire'.$i,
                'emp_sex' => 'other',
                'status' => 'active',
            ])->employee_number;
        }
        echo json_encode($numbers);
        break;

    case 'report':
        echo json_encode([
            'numbers' => Employee::orderBy('id')->pluck('employee_number'),
            'next_number' => NumberSequence::where('key', NumberSequence::EMPLOYEE_NUMBER)->value('next_number'),
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
