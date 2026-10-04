<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB races for recruitment phase 5 (accepted offer → employee):
 * one conversion, one employee, one employee number, one hiring movement, no
 * duplicate history or login; unique numbers across candidates; full rollback.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class RecruitmentPhase5ConcurrencyTest extends TestCase
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

    private function ok(array $results): array
    {
        return array_values(array_filter($results, fn ($r) => str_starts_with($r, 'ok')));
    }

    /** Employees created by conversions (the fixture people have letter numbers like DINA). */
    private function hired(array $report): array
    {
        return array_values(array_filter($report['employees'], fn ($e) => str_starts_with($e['employee_number'], 'EMP-')));
    }

    #[Test]
    public function six_simultaneous_conversions_of_one_accepted_offer_convert_exactly_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $app = trim($this->worker(['accepted-application', $vacancy]));
        $users = count(json_decode($this->worker(['report']), true)['users']);

        $results = $this->race(array_fill(0, 6, ['convert', $app, 'new.hire']));
        $report = json_decode($this->worker(['report']), true);

        // 1. exactly one conversion succeeds, the rest get a business message
        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => ! str_starts_with($r, 'ok')) as $denied) {
            $this->assertMatchesRegularExpression('/already been converted|username is already taken/', $denied);
        }
        // 2. exactly one employee
        $hired = $this->hired($report);
        $this->assertCount(1, $hired);
        // 3. exactly one employee number issued, and the sequence moved by one
        $this->assertSame(sprintf('EMP-%05d', 1), $hired[0]['employee_number']);
        $this->assertSame(2, $report['employee_sequence_next']);
        // 4. exactly one hiring movement, for that employee
        $this->assertSame([['employee_id' => $hired[0]['id']]], $report['hiring_movements']);
        // 5. exactly one conversion record
        $this->assertCount(1, $report['conversions']);
        $this->assertSame($hired[0]['id'], $report['conversions'][0]['employee_id']);
        // 6. no duplicate education / work experience
        $this->assertCount(2, array_filter($report['educations'], fn ($e) => $e['employee_id'] === $hired[0]['id']));
        $this->assertCount(2, array_filter($report['work_experiences'], fn ($w) => $w['employee_id'] === $hired[0]['id']));
        // 7. no duplicate login
        $this->assertCount($users + 1, $report['users']);
        $this->assertSame([['username' => 'new.hire', 'employee_id' => $hired[0]['id']]], array_values(array_filter($report['users'], fn ($u) => $u['username'] === 'new.hire')));
        $this->assertCount(1, array_filter($report['offer_events'], fn ($e) => $e['event'] === 'converted'));
    }

    #[Test]
    public function many_candidates_converted_at_once_each_get_one_employee_and_a_unique_number(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '5']));
        $apps = array_map(fn () => trim($this->worker(['accepted-application', $vacancy])), range(1, 5));

        $results = $this->race(array_map(fn ($id) => ['convert', $id], $apps));
        $report = json_decode($this->worker(['report']), true);

        $this->assertCount(5, $this->ok($results), implode(' | ', $results));
        $numbers = array_map(fn ($r) => substr($r, 3), $results);
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('EMP-%05d', $n), range(1, 5)), $numbers);
        $this->assertCount(5, $this->hired($report));
        $this->assertCount(5, array_unique(array_column($report['conversions'], 'employee_id')));
        $this->assertCount(5, $report['hiring_movements']);
        $this->assertSame(6, $report['employee_sequence_next']);
    }

    #[Test]
    public function a_conversion_failing_midway_leaves_nothing_behind_and_consumes_no_number(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '2']));
        $broken = trim($this->worker(['accepted-application', $vacancy, 'broken']));
        $good = trim($this->worker(['accepted-application', $vacancy]));
        $before = json_decode($this->worker(['report']), true);

        $result = trim($this->worker(['convert', $broken, 'broken.user', '0']));
        $this->assertStringContainsString('nothing was saved', $result);

        $after = json_decode($this->worker(['report']), true);
        foreach (['employees', 'conversions', 'hiring_movements', 'educations', 'work_experiences', 'users', 'employee_sequence_next'] as $key) {
            $this->assertSame($before[$key], $after[$key], $key);
        }

        // The number the failed attempt drew was rolled back with it.
        $this->assertSame('ok:EMP-00001', trim($this->worker(['convert', $good, '0'])));
    }

    #[Test]
    public function simultaneous_internal_candidate_conversions_link_the_existing_employee_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $app = trim($this->worker(['accepted-application', $vacancy, 'internal']));
        $before = json_decode($this->worker(['report']), true);

        $results = $this->race(array_fill(0, 6, ['convert', $app]));
        $report = json_decode($this->worker(['report']), true);

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        $this->assertSame('ok:OTTO', $this->ok($results)[0]);
        $this->assertCount(1, $report['conversions']);
        $this->assertSame(['existing_employee', null], [$report['conversions'][0]['conversion_type'], $report['conversions'][0]['employee_movement_id']]);
        foreach (['employees', 'hiring_movements', 'educations', 'work_experiences', 'users', 'employee_sequence_next'] as $key) {
            $this->assertSame($before[$key], $report[$key], $key);
        }
    }

    #[Test]
    public function conversion_timestamps_are_never_rewritten_by_the_database(): void
    {
        $result = json_decode($this->worker(['p5-timestamp-stability']), true);

        $this->assertCount(1, $result['before']);
        $this->assertSame($result['before'], $result['after']);
    }

    #[Test]
    public function no_phase5_timestamp_column_auto_updates(): void
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
