<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB races for employee onboarding: one active case per
 * employee, one task completion, one onboarding completion, no completion while
 * a required task is open, independent employees, no duplicate task instances.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class OnboardingConcurrencyTest extends TestCase
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

    private function report(): array
    {
        return json_decode($this->worker(['report']), true);
    }

    #[Test]
    public function six_simultaneous_starts_for_one_employee_create_one_active_onboarding_without_duplicate_tasks(): void
    {
        $results = $this->race(array_fill(0, 6, ['start', 'nina']));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach (array_diff($results, ['ok']) as $denied) {
            $this->assertStringContainsString('already has an active onboarding', $denied);
        }
        $report = $this->report();
        $this->assertCount(1, $report['onboardings']);
        $this->assertSame('pending', $report['onboardings'][0]['status']);
        // The template was applied exactly once: one task per template task.
        $this->assertCount(4, $report['tasks']);
        $this->assertCount(4, array_unique(array_column($report['tasks'], 'onboarding_template_task_id')));
        $this->assertCount(1, array_filter($report['events'], fn ($e) => $e['event'] === 'created'));
    }

    #[Test]
    public function different_employees_start_onboarding_independently_at_once(): void
    {
        $results = $this->race(array_map(fn ($u) => ['start', $u], ['nina', 'ollie', 'pia', 'quinn', 'rex', 'sue']));

        $this->assertSame(array_fill(0, 6, 'ok'), $results);
        $report = $this->report();
        $this->assertCount(6, $report['onboardings']);
        $this->assertCount(6, array_unique(array_column($report['onboardings'], 'employee_id')));
        $this->assertCount(24, $report['tasks']);
    }

    #[Test]
    public function six_simultaneous_completions_of_one_task_complete_it_once(): void
    {
        $this->worker(['start', 'nina', '0']);
        $case = $this->report()['onboardings'][0]['id'];
        $task = trim($this->worker(['task', (string) $case, 'Prepare workstation']));

        $results = $this->race([...array_fill(0, 3, ['complete-task', $task, 'hana']), ...array_fill(0, 3, ['complete-task', $task, 'mona'])]);

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $report = $this->report();
        $final = array_values(array_filter($report['tasks'], fn ($t) => (string) $t['id'] === $task))[0];
        $this->assertSame('completed', $final['status']);
        $this->assertNotNull($final['completed_by']);
        $this->assertCount(1, array_filter($report['events'], fn ($e) => $e['event'] === 'task_completed'));
        $this->assertCount(1, array_filter($report['events'], fn ($e) => $e['event'] === 'started'));
        $this->assertSame('in_progress', $report['onboardings'][0]['status']);
    }

    #[Test]
    public function six_simultaneous_onboarding_completions_complete_it_once(): void
    {
        $case = trim($this->worker(['ready', 'nina', 'all']));

        $results = $this->race(array_fill(0, 6, ['complete', $case]));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach (array_diff($results, ['ok']) as $denied) {
            $this->assertStringContainsString('already completed', $denied);
        }
        $report = $this->report();
        $this->assertSame('completed', $report['onboardings'][0]['status']);
        $this->assertCount(1, array_filter($report['events'], fn ($e) => $e['event'] === 'completed'));
    }

    #[Test]
    public function no_completion_succeeds_while_a_required_task_is_open(): void
    {
        $case = trim($this->worker(['ready', 'nina', 'missing']));

        $results = $this->race(array_fill(0, 6, ['complete', $case]));

        $this->assertSame([], array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach ($results as $denied) {
            $this->assertStringContainsString('1 required task(s) remain incomplete', $denied);
        }
        $report = $this->report();
        $this->assertSame('in_progress', $report['onboardings'][0]['status']);
        $this->assertCount(0, array_filter($report['events'], fn ($e) => $e['event'] === 'completed'));
    }

    #[Test]
    public function completing_the_last_required_task_and_the_onboarding_at_once_never_completes_early(): void
    {
        $case = trim($this->worker(['ready', 'nina', 'missing']));
        $task = trim($this->worker(['task', $case, 'Sign handbook']));

        $results = $this->race([['complete-task', $task, 'nina'], ...array_fill(0, 5, ['complete', $case])]);

        $report = $this->report();
        $this->assertSame('ok', $results[0], implode(' | ', $results));
        $completedEvents = array_values(array_filter($report['events'], fn ($e) => in_array($e['event'], ['task_completed', 'completed'], true)));
        $wins = count(array_filter(array_slice($results, 1), fn ($r) => $r === 'ok'));
        $this->assertLessThanOrEqual(1, $wins);
        if ($wins === 1) {
            $this->assertSame('completed', end($completedEvents)['event'], 'the case completed only after its last required task');
        }
        $this->assertSame($wins === 1 ? 'completed' : 'in_progress', $report['onboardings'][0]['status']);
    }

    #[Test]
    public function onboarding_timestamps_are_never_rewritten_by_the_database(): void
    {
        $result = json_decode($this->worker(['timestamp-stability']), true);

        $this->assertNotEmpty($result['before']['onboarding_tasks']);
        $this->assertSame($result['before'], $result['after']);
    }

    #[Test]
    public function no_onboarding_timestamp_column_auto_updates(): void
    {
        $this->assertSame([], json_decode($this->worker(['schema-onupdate']), true));
    }

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
        $process = proc_open([PHP_BINARY, __DIR__.'/onboarding_worker.php', ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $this->env());
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
