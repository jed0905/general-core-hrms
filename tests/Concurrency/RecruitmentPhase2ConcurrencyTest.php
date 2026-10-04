<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB races for recruitment phase 2: applicant and application
 * numbers, duplicate applications, duplicate applicants and stage moves.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class RecruitmentPhase2ConcurrencyTest extends TestCase
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
    public function concurrent_applicants_get_unique_gap_free_numbers(): void
    {
        $issued = array_merge(...array_map('json_decode', $this->race(array_fill(0, 6, ['applicants', '15']))));

        $this->assertCount(90, array_unique($issued));
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('APP-%s-%05d', date('Y'), $n), range(1, 90)), $issued);
    }

    #[Test]
    public function concurrent_applications_get_unique_gap_free_numbers(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $issued = array_merge(...array_map('json_decode', $this->race(array_fill(0, 6, ['apply-many', $vacancy, '15']))));

        $this->assertCount(90, array_unique($issued));
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('APL-%s-%05d', date('Y'), $n), range(1, 90)), $issued);
    }

    #[Test]
    public function the_same_application_submitted_simultaneously_is_created_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $applicant = trim($this->worker(['new-applicant']));

        $results = $this->race(array_fill(0, 6, ['apply', $applicant, $vacancy]));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => $r !== 'ok') as $denied) {
            $this->assertStringContainsString('already applied', $denied);
        }
        $this->assertCount(1, json_decode($this->worker(['report']), true)['applications']);
    }

    #[Test]
    public function the_same_person_registered_simultaneously_is_created_once(): void
    {
        $results = $this->race(array_fill(0, 6, ['register-same', 'Twin.Person@Example.com']));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $emails = json_decode($this->worker(['report']), true)['applicant_emails'];
        $this->assertSame(['twin.person@example.com'], $emails);
    }

    #[Test]
    public function simultaneous_stage_moves_move_the_application_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $app = json_decode($this->worker(['new-application', $vacancy]), true);

        $results = $this->race(array_fill(0, 6, ['advance', (string) $app['id'], (string) $app['stage']]));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $report = json_decode($this->worker(['report']), true);
        $this->assertSame(['applied', 'moved'], array_column($report['history'], 'action'), 'one move recorded');
        $this->assertNotSame($app['stage'], $report['applications'][0]['current_vacancy_stage_id']);
    }

    #[Test]
    public function no_recruitment_or_document_timestamp_auto_updates_on_mysql(): void
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
