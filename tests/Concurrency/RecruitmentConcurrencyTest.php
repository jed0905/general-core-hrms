<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB concurrency checks for recruitment: number generation,
 * approval decisions, vacancy status changes and requisition allocation, each
 * raced by several PHP processes starting at the same instant.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class RecruitmentConcurrencyTest extends TestCase
{
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
    public function concurrent_requisitions_get_unique_gap_free_numbers(): void
    {
        $issued = array_merge(...array_map('json_decode', $this->race(array_fill(0, 6, ['requisitions', '20']))));
        $year = date('Y');

        $this->assertCount(120, array_unique($issued));
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('REQ-%s-%05d', $year, $n), range(1, 120)), $issued);
    }

    #[Test]
    public function concurrent_vacancies_get_unique_gap_free_numbers(): void
    {
        $issued = array_merge(...array_map('json_decode', $this->race(array_fill(0, 6, ['vacancies', '20']))));
        $year = date('Y');

        $this->assertCount(120, array_unique($issued));
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('VAC-%s-%05d', $year, $n), range(1, 120)), $issued);
    }

    #[Test]
    public function only_one_of_several_simultaneous_approvals_of_a_step_succeeds(): void
    {
        $id = trim($this->worker(['submitted-requisition']));

        $results = $this->race(array_fill(0, 6, ['approve', $id]));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $approvals = json_decode($this->worker(['report']), true)['approvals'];
        $this->assertSame(['approved', 'pending'], array_column($approvals, 'status'), 'step 1 approved once; step 2 untouched');
    }

    #[Test]
    public function simultaneous_status_changes_leave_exactly_one_winner(): void
    {
        $id = trim($this->worker(['open-vacancy']));

        $results = $this->race([
            ['transition', $id, 'closed'], ['transition', $id, 'cancelled'], ['transition', $id, 'filled'],
            ['transition', $id, 'closed'], ['transition', $id, 'cancelled'], ['transition', $id, 'filled'],
        ]);

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $status = json_decode($this->worker(['report']), true)['vacancies'][0]['status'];
        $this->assertContains($status, ['closed', 'cancelled', 'filled']);
    }

    #[Test]
    public function simultaneous_vacancies_cannot_over_allocate_a_requisition(): void
    {
        $id = trim($this->worker(['approved-requisition', '3']));

        $results = $this->race(array_fill(0, 4, ['allocate', $id, '2']));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $allocated = array_sum(array_column(json_decode($this->worker(['report']), true)['vacancies'], 'openings'));
        $this->assertSame(2, $allocated, 'never more than the 3 approved positions');
    }

    /**
     * Start all commands at the same instant; returns each process's output in order.
     *
     * @param  array<int, array<int, string>>  $commands
     */
    private function race(array $commands): array
    {
        $start = (string) (microtime(true) + 3);
        $processes = array_map(fn ($args) => $this->spawn([...$args, $start]), $commands);

        return array_map(function ($p) {
            [$process, $pipes] = $p;
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), "worker failed: {$err}");

            return trim($out);
        }, $processes);
    }

    private function env(): array
    {
        return ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $this->database, 'DB_URL' => '', 'DATABASE_URL' => '', 'APP_ENV' => 'local'] + getenv();
    }

    private function spawn(array $args): array
    {
        $process = proc_open([PHP_BINARY, __DIR__.'/recruitment_worker.php', ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $this->env());
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
        if (($code = proc_close($process)) !== 0) {
            $this->fail("worker {$args[0]} failed ({$code}): {$err}");
        }

        return $out;
    }
}
