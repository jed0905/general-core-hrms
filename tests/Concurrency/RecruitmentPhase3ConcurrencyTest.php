<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB races for recruitment phase 3: stage moves into and
 * through Interview/Assessment, panelist double booking, one scorecard per
 * evaluator, and lifecycle timestamps the database must never rewrite.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class RecruitmentPhase3ConcurrencyTest extends TestCase
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
    public function simultaneous_shortlisted_to_interview_moves_happen_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $app = json_decode($this->worker(['shortlisted-application', $vacancy]), true);

        $results = $this->race(array_fill(0, 6, ['advance', (string) $app['id'], (string) $app['stage']]));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => $r !== 'ok') as $denied) {
            $this->assertStringContainsString('already moved', $denied);
        }
        $history = json_decode($this->worker(['report']), true)['history'];
        $this->assertCount(1, array_filter($history, fn ($h) => $h['to_stage_name'] === 'Interview'), 'one move recorded');
    }

    #[Test]
    public function simultaneous_interview_to_assessment_moves_happen_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $app = json_decode($this->worker(['interviewing-application', $vacancy, 'eve']), true);

        $results = $this->race(array_fill(0, 6, ['advance', (string) $app['id'], (string) $app['stage']]));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        $history = json_decode($this->worker(['report']), true)['history'];
        $this->assertCount(1, array_filter($history, fn ($h) => $h['to_stage_name'] === 'Assessment'));
    }

    #[Test]
    public function a_panelist_cannot_be_double_booked_by_simultaneous_schedulers(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $apps = array_map(fn () => json_decode($this->worker(['interviewing-application', $vacancy]), true)['id'], range(1, 6));
        $date = date('Y-m-d', strtotime('+7 days'));

        // Six HR users book Maya for overlapping slots (10:00, 10:10, ... 10:50) for six different applicants.
        $results = $this->race(array_map(fn ($id, $i) => ['schedule', (string) $id, 'maya', $date, sprintf('10:%02d', $i * 10)], $apps, array_keys($apps)));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => $r !== 'ok') as $denied) {
            $this->assertStringContainsString('Already booked for an overlapping interview', $denied);
        }
        $report = json_decode($this->worker(['report']), true);
        $this->assertCount(1, $report['interviews']);
        $this->assertCount(1, $report['panelists']);
    }

    #[Test]
    public function non_overlapping_simultaneous_bookings_all_succeed(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $apps = array_map(fn () => json_decode($this->worker(['interviewing-application', $vacancy]), true)['id'], range(1, 4));
        $date = date('Y-m-d', strtotime('+7 days'));

        $results = $this->race(array_map(fn ($id, $i) => ['schedule', (string) $id, 'maya', $date, sprintf('%02d:00', 9 + $i)], $apps, array_keys($apps)));

        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $results);
        $this->assertCount(4, json_decode($this->worker(['report']), true)['interviews']);
    }

    #[Test]
    public function simultaneous_scorecard_submissions_create_one_scorecard(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $app = json_decode($this->worker(['interviewing-application', $vacancy]), true);
        $interview = trim($this->worker(['completed-interview', (string) $app['id'], 'eve']));

        $results = $this->race(array_fill(0, 6, ['evaluate', $interview, 'eve', 'submit']));

        $this->assertCount(1, array_filter($results, fn ($r) => $r === 'ok'), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => $r !== 'ok') as $denied) {
            $this->assertStringContainsString('already been submitted', $denied);
        }
        $report = json_decode($this->worker(['report']), true);
        $this->assertCount(1, $report['evaluations']);
        $this->assertSame('submitted', $report['evaluations'][0]['status']);
        $this->assertSame(6, $report['evaluation_scores']);
    }

    #[Test]
    public function simultaneous_draft_saves_keep_a_single_scorecard(): void
    {
        $vacancy = trim($this->worker(['open-vacancy']));
        $app = json_decode($this->worker(['interviewing-application', $vacancy]), true);
        $interview = trim($this->worker(['completed-interview', (string) $app['id'], 'eve']));

        $results = $this->race(array_fill(0, 6, ['evaluate', $interview, 'eve', 'draft']));

        $this->assertSame(array_fill(0, 6, 'ok'), $results);
        $report = json_decode($this->worker(['report']), true);
        $this->assertCount(1, $report['evaluations']);
        $this->assertSame(['draft', 6], [$report['evaluations'][0]['status'], $report['evaluation_scores']]);
    }

    #[Test]
    public function lifecycle_timestamps_are_never_rewritten_by_the_database(): void
    {
        $result = json_decode($this->worker(['timestamp-stability']), true);

        $this->assertNotEmpty($result['before']['application_interviews']);
        $this->assertNotEmpty($result['before']['interview_reschedules']);
        $this->assertNotEmpty($result['before']['application_assessments']);
        $this->assertNotEmpty($result['before']['application_evaluations']);
        $this->assertNotNull($result['before']['application_evaluations'][0]['submitted_at']);
        $this->assertSame($result['before'], $result['after']);
    }

    #[Test]
    public function no_phase3_timestamp_column_auto_updates(): void
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
