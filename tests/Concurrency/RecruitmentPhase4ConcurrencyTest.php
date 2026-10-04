<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real MySQL/MariaDB races for recruitment phase 4: selection against vacancy
 * openings, one active offer per application, offer numbers, approval
 * decisions, issuing, candidate responses and lifecycle timestamps.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class RecruitmentPhase4ConcurrencyTest extends TestCase
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
        return array_values(array_filter($results, fn ($r) => $r === 'ok' || str_starts_with($r, 'ok:')));
    }

    #[Test]
    public function the_last_opening_goes_to_exactly_one_of_several_candidates(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $apps = array_map(fn () => trim($this->worker(['evaluated-application', $vacancy])), range(1, 4));

        $results = $this->race(array_map(fn ($id) => ['select', $id], $apps));

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        foreach (array_diff($results, ['ok']) as $denied) {
            $this->assertStringContainsString('already taken by selected candidates', $denied);
        }
        $report = json_decode($this->worker(['report']), true);
        $this->assertSame([1, 1], [$report['vacancies'][0]['openings'], $report['vacancies'][0]['filled_count']]);
        $this->assertCount(1, $report['selections']);
    }

    #[Test]
    public function two_openings_are_never_overfilled(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '2']));
        $apps = array_map(fn () => trim($this->worker(['evaluated-application', $vacancy])), range(1, 5));

        $results = $this->race(array_map(fn ($id) => ['select', $id], $apps));

        $this->assertCount(2, $this->ok($results), implode(' | ', $results));
        $report = json_decode($this->worker(['report']), true);
        $this->assertSame(2, $report['vacancies'][0]['filled_count']);
        $this->assertCount(2, $report['selections']);
    }

    #[Test]
    public function the_same_candidate_selected_simultaneously_is_selected_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '3']));
        $app = trim($this->worker(['evaluated-application', $vacancy]));

        $results = $this->race(array_fill(0, 6, ['select', $app]));

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        foreach (array_diff($results, ['ok']) as $denied) {
            $this->assertStringContainsString('already selected', $denied);
        }
        $report = json_decode($this->worker(['report']), true);
        $this->assertCount(1, $report['selections']);
        $this->assertSame(1, $report['vacancies'][0]['filled_count']);
    }

    #[Test]
    public function simultaneous_offers_for_one_candidate_create_one_active_offer(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $app = trim($this->worker(['selected-application', $vacancy]));

        $results = $this->race(array_fill(0, 6, ['create-offer', $app]));

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        foreach (array_filter($results, fn ($r) => str_starts_with($r, 'denied')) as $denied) {
            $this->assertStringContainsString('already has', $denied);
        }
        $offers = json_decode($this->worker(['report']), true)['offers'];
        $this->assertCount(1, $offers);
        $this->assertSame(sprintf('OFF-%s-00001', date('Y')), $offers[0]['offer_number'], 'a refused attempt gives its number back');
    }

    #[Test]
    public function offer_numbers_are_unique_and_gap_free_under_load(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '6']));
        $apps = array_map(fn () => trim($this->worker(['selected-application', $vacancy])), range(1, 6));

        $results = $this->race(array_map(fn ($id) => ['create-offer', $id], $apps));

        $this->assertCount(6, $this->ok($results), implode(' | ', $results));
        $numbers = array_map(fn ($r) => substr($r, 3), $results);
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => sprintf('OFF-%s-%05d', date('Y'), $n), range(1, 6)), $numbers);
    }

    #[Test]
    public function the_same_approval_step_is_approved_once(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $offer = trim($this->worker(['submitted-offer', $vacancy]));

        $results = $this->race(array_fill(0, 6, ['approve-offer', $offer, 'mona']));

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        $report = json_decode($this->worker(['report']), true);
        $this->assertSame([['approved', 1], ['pending', 2]], array_map(fn ($a) => [$a['status'], $a['approval_order']], $report['offer_approvals']));
        $this->assertSame('pending_approval', $report['offers'][0]['status']);
        $this->assertCount(1, array_filter($report['offer_events'], fn ($e) => $e['event'] === 'step_approved'));
    }

    #[Test]
    public function an_approve_and_reject_race_has_one_winner(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $offer = trim($this->worker(['submitted-offer', $vacancy]));

        $results = $this->race([...array_fill(0, 3, ['approve-offer', $offer, 'mona']), ...array_fill(0, 3, ['reject-offer', $offer, 'mona'])]);

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        $report = json_decode($this->worker(['report']), true);
        $step1 = $report['offer_approvals'][0]['status'];
        $this->assertContains($step1, ['approved', 'rejected']);
        $this->assertSame($step1 === 'approved' ? 'pending_approval' : 'rejected', $report['offers'][0]['status']);
        $this->assertCount(1, array_filter($report['offer_events'], fn ($e) => in_array($e['event'], ['step_approved', 'approval_rejected'], true)));
    }

    #[Test]
    public function issuing_while_the_final_approval_is_in_flight_never_issues_an_unapproved_offer(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $offer = trim($this->worker(['submitted-offer', $vacancy]));
        $this->worker(['approve-offer', $offer, 'mona', '0']);

        $results = $this->race([['approve-offer', $offer, 'dina'], ...array_fill(0, 5, ['issue-offer', $offer])]);

        $report = json_decode($this->worker(['report']), true);
        $events = array_column($report['offer_events'], 'event');
        $this->assertSame('ok', $results[0], implode(' | ', $results));
        $issued = in_array('issued', $events, true);
        // Either nobody issued (they all ran before approval) or exactly one issue happened after approval.
        $this->assertCount($issued ? 1 : 0, array_filter(array_slice($results, 1), fn ($r) => $r === 'ok'));
        if ($issued) {
            $this->assertLessThan(array_search('issued', $events, true), array_search('approved', $events, true));
        }
        $this->assertSame($issued ? 'issued' : 'approved', $report['offers'][0]['status']);
    }

    #[Test]
    public function different_candidate_responses_at_once_record_one_response(): void
    {
        $vacancy = trim($this->worker(['open-vacancy', '1']));
        $offer = trim($this->worker(['issued-offer', $vacancy]));

        $results = $this->race([...array_fill(0, 3, ['respond-offer', $offer, 'accepted']), ...array_fill(0, 3, ['respond-offer', $offer, 'declined'])]);

        $this->assertCount(1, $this->ok($results), implode(' | ', $results));
        $report = json_decode($this->worker(['report']), true);
        $final = $report['offers'][0];
        $this->assertContains($final['status'], ['accepted', 'declined']);
        $this->assertSame($final['status'], $final['response']);
        $this->assertCount(1, array_filter($report['offer_events'], fn ($e) => str_starts_with($e['event'], 'candidate_')));
    }

    #[Test]
    public function phase4_lifecycle_timestamps_are_never_rewritten_by_the_database(): void
    {
        $result = json_decode($this->worker(['p4-timestamp-stability']), true);

        $this->assertEqualsCanonicalizing(['expired', 'accepted', 'withdrawn', 'rejected'], $result['statuses']);
        foreach (['application_selections', 'job_offers', 'job_offer_approvals', 'job_offer_events'] as $table) {
            $this->assertNotEmpty($result['before'][$table], $table);
        }
        $this->assertSame($result['before'], $result['after']);
    }

    #[Test]
    public function no_phase4_timestamp_column_auto_updates(): void
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
