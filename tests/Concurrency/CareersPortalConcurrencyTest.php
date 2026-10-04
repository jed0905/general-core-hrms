<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB races for the careers portal (recruitment phase 7):
 * duplicate online applications, many candidates at once, documents from
 * duplicate submissions, application vs closure / unpublishing, candidate
 * withdrawal vs HR stage moves, and concurrent profile edits.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class CareersPortalConcurrencyTest extends TestCase
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

    private function ok(array $results): array
    {
        return array_values(array_filter($results, fn ($r) => str_starts_with($r, 'ok')));
    }

    #[Test]
    public function six_identical_online_submissions_create_one_application_and_one_document(): void
    {
        $vacancy = json_decode($this->worker(['published-vacancy']), true);
        $account = trim($this->worker(['portal-candidate']));

        $results = $this->race(array_fill(0, 6, ['portal-apply', $account, $vacancy['slug']]));

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => ! str_starts_with($r, 'ok')) as $denied) {
            $this->assertStringContainsString('already applied', $denied);
        }
        $report = $this->report();
        $this->assertCount(1, $report['applications']);
        // Duplicate submissions leave no extra documents, links or files behind.
        $this->assertCount(1, $report['applicant_documents']);
        $this->assertSame(1, $report['application_documents']);
        $this->assertSame(1, $report['stored_files']);
        $this->assertCount(1, array_filter($report['portal_events'], fn ($e) => $e['event'] === 'application_submitted'));
        $this->assertSame(0, $report['vacancies'][0]['filled_count'], 'applying never takes an opening');
    }

    #[Test]
    public function six_different_candidates_apply_at_once_and_all_succeed(): void
    {
        $vacancy = json_decode($this->worker(['published-vacancy']), true);
        $accounts = array_map(fn () => trim($this->worker(['portal-candidate'])), range(1, 6));

        $results = $this->race(array_map(fn ($a) => ['portal-apply', $a, $vacancy['slug']], $accounts));

        $this->assertCount(6, $this->ok($results), implode(' | ', $results));
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('ok:APL-%s-%05d', date('Y'), $n), range(1, 6)), $results);
        $report = $this->report();
        $this->assertCount(6, $report['applications']);
        $this->assertSame(6, $report['stored_files']);
    }

    #[Test]
    public function applications_racing_a_vacancy_closure_never_land_after_it(): void
    {
        $vacancy = json_decode($this->worker(['published-vacancy']), true);
        $accounts = array_map(fn () => trim($this->worker(['portal-candidate'])), range(1, 5));

        $results = $this->race([['transition', (string) $vacancy['id'], 'closed'], ...array_map(fn ($a) => ['portal-apply', $a, $vacancy['slug']], $accounts)]);

        $this->assertSame('ok', $results[0], implode(' | ', $results));
        $report = $this->report();
        $applied = $this->ok(array_slice($results, 1));
        foreach (array_diff(array_slice($results, 1), $applied) as $denied) {
            $this->assertStringContainsString('no longer accepting applications', $denied);
        }
        $this->assertCount(count($applied), $report['applications'], 'only applications committed before the closure exist');
        $this->assertSame('closed', $report['vacancies'][0]['status']);
        $this->assertCount(count($applied), $report['applicant_documents'], 'refused attempts leave no documents');
        $this->assertSame(count($applied), $report['stored_files'], 'and no files');
    }

    #[Test]
    public function applications_racing_an_unpublish_never_land_after_it(): void
    {
        $vacancy = json_decode($this->worker(['published-vacancy']), true);
        $accounts = array_map(fn () => trim($this->worker(['portal-candidate'])), range(1, 5));

        $results = $this->race([['careers-toggle', (string) $vacancy['id'], 'unpublish'], ...array_map(fn ($a) => ['portal-apply', $a, $vacancy['slug']], $accounts)]);

        $this->assertSame('ok', $results[0], implode(' | ', $results));
        $report = $this->report();
        $applied = $this->ok(array_slice($results, 1));
        $this->assertCount(count($applied), $report['applications']);
        $this->assertNull($report['vacancy_publication'][0]['published_at']);
        $this->assertSame(count($applied), $report['stored_files']);
    }

    #[Test]
    public function a_candidate_withdrawal_racing_an_hr_stage_move_ends_in_a_valid_state(): void
    {
        $vacancy = json_decode($this->worker(['published-vacancy']), true);
        $account = trim($this->worker(['portal-candidate']));
        $number = substr(trim($this->worker(['portal-apply', $account, $vacancy['slug'], '0'])), 3);
        $app = $this->report()['applications'][0];

        $results = $this->race([...array_fill(0, 3, ['portal-withdraw', $account, $number]), ...array_fill(0, 3, ['advance', (string) $app['id'], (string) $app['current_vacancy_stage_id']])]);

        $report = $this->report();
        $withdrawals = $this->ok(array_slice($results, 0, 3));
        $moves = $this->ok(array_slice($results, 3));
        $this->assertCount(1, $withdrawals, implode(' | ', $results)); // withdrawal works from either stage
        $this->assertLessThanOrEqual(1, count($moves));
        $actions = array_column(array_filter($report['history'], fn ($h) => $h['application_id'] === $app['id']), 'action');
        $this->assertSame('withdrawn', $report['applications'][0]['status']);
        $this->assertSame(1, count(array_keys($actions, 'withdrawn')));
        $this->assertSame(count($moves), count(array_keys($actions, 'moved')));
        if ($moves) {
            $this->assertLessThan(array_search('withdrawn', $actions), array_search('moved', $actions), 'a move can only precede the withdrawal');
        }
    }

    #[Test]
    public function concurrent_profile_updates_leave_one_consistent_record(): void
    {
        $account = trim($this->worker(['portal-candidate']));

        $results = $this->race(array_map(fn ($n) => ['portal-profile', $account, (string) $n], range(1, 6)));

        $this->assertCount(6, $this->ok($results), implode(' | ', $results));
        $report = $this->report();
        $applicant = $report['applicants'][0];
        $n = (int) substr($applicant['address'], 7);
        $this->assertSame('0917 55'.str_pad((string) $n, 5, '0', STR_PAD_LEFT), $applicant['phone'], 'phone and address from the same update');
        $this->assertSame('091755'.str_pad((string) $n, 5, '0', STR_PAD_LEFT), substr('0'.$applicant['phone_key'], -11), 'derived key matches');
        $this->assertSame([['applicant_id' => $applicant['id'], 'institute' => "School {$n}"]], $report['applicant_educations'], 'education replaced, never duplicated');
    }

    #[Test]
    public function no_careers_timestamp_column_auto_updates(): void
    {
        // Includes vacancies.published_at and the portal tables.
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
