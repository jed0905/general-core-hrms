<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real concurrency check for employee-number generation: several PHP processes
 * create employees at the same moment against MySQL/MariaDB, and every issued
 * number must be unique and gap-free.
 *
 * Not part of the default suites (SQLite has no row locks). Opt in with a scratch
 * database name that ends with "_concurrency_test"; it is created, migrated and
 * dropped by the test. Uses the DB_HOST/DB_USERNAME/DB_PASSWORD from .env.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class EmployeeNumberConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    private const PER_WORKER = 25;

    private ?string $database = null;

    protected function setUp(): void
    {
        $database = getenv('HRMS_CONCURRENCY_DB') ?: null;

        if (! $database) {
            $this->markTestSkipped('Set HRMS_CONCURRENCY_DB=<name>_concurrency_test to run against MySQL.');
        }
        if (! preg_match('/^[A-Za-z0-9_]+_concurrency_test$/', $database)) {
            $this->fail('HRMS_CONCURRENCY_DB must end with _concurrency_test.');
        }

        $this->database = $database;
        $this->worker(['setup']);
    }

    protected function tearDown(): void
    {
        if ($this->database) {
            $this->worker(['teardown']);
        }
    }

    #[Test]
    public function concurrent_creations_never_share_or_skip_a_number(): void
    {
        $startAt = microtime(true) + 3; // all workers begin at the same instant
        $processes = [];

        for ($w = 0; $w < self::WORKERS; $w++) {
            $processes[] = $this->spawn(['create', (string) self::PER_WORKER, (string) $startAt]);
        }

        $issued = [];
        foreach ($processes as [$process, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), "worker failed: {$err}");
            $issued = array_merge($issued, json_decode($out, true));
        }

        $total = self::WORKERS * self::PER_WORKER;
        $report = json_decode($this->worker(['report']), true);
        $expected = array_map(fn ($n) => sprintf('EMP-%05d', $n), range(1, $total));

        $this->assertCount($total, $issued);
        $this->assertCount($total, array_unique($issued), 'no number was issued twice');
        $this->assertEqualsCanonicalizing($expected, $issued, 'numbers are consecutive with no gaps');
        $this->assertEqualsCanonicalizing($expected, $report['numbers'], 'stored employees match what was issued');
        $this->assertSame($total + 1, (int) $report['next_number']);
    }

    private function env(): array
    {
        // Explicit values win over phpunit.xml's forced SQLite settings and are not read from .env.
        return ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $this->database, 'DB_URL' => '', 'DATABASE_URL' => '', 'APP_ENV' => 'local'] + getenv();
    }

    private function spawn(array $args): array
    {
        $cmd = array_merge([PHP_BINARY, __DIR__.'/employee_number_worker.php'], $args);
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $this->env());
        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    private function worker(array $args): string
    {
        [$process, $pipes] = $this->spawn($args);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            $this->fail("worker {$args[0]} failed ({$code}): {$err}");
        }

        return $out;
    }
}
