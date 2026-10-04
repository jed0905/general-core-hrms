<?php

namespace Tests\Concurrency;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Recruitment phase 8 on real MySQL/MariaDB: the recruitment reports are
 * read-only (no statement other than SELECT, table checksums unchanged),
 * give the same answer when run concurrently, and never block or break the
 * recruitment writes they race with.
 *
 *   HRMS_CONCURRENCY_DB=hrms_concurrency_test vendor/bin/phpunit tests/Concurrency
 */
#[Group('mysql-concurrency')]
class RecruitmentReportingConcurrencyTest extends TestCase
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

    /** A pipeline with every kind of record the reports read. */
    private function fixture(): int
    {
        $vacancy = (int) trim($this->worker(['open-vacancy', '6']));
        $hired = trim($this->worker(['accepted-application', (string) $vacancy]));
        $this->assertStringStartsWith('ok:', trim($this->worker(['convert', $hired, (string) microtime(true)])));
        $this->worker(['issued-offer', (string) $vacancy]);
        $this->worker(['interviewing-application', (string) $vacancy, 'eve']);
        $this->worker(['apply-many', (string) $vacancy, '3', (string) microtime(true)]);
        $published = json_decode($this->worker(['published-vacancy']), true);
        $account = trim($this->worker(['portal-candidate']));
        $this->assertStringStartsWith('ok', trim($this->worker(['portal-apply', $account, $published['slug'], (string) microtime(true)])));

        return $vacancy;
    }

    private function period(): array
    {
        return [date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('+1 day'))];
    }

    #[Test]
    public function six_concurrent_report_runs_change_nothing_and_agree(): void
    {
        $this->fixture();
        $before = json_decode($this->worker(['checksums']), true);

        $results = $this->race(array_fill(0, 6, ['run-reports', ...$this->period()]));

        $this->assertSame($before, json_decode($this->worker(['checksums']), true), 'table checksums changed');
        $this->assertCount(1, array_unique($results), implode(' | ', $results));
        $this->assertStringStartsWith('ok:', $results[0], 'a report issued a non-SELECT statement');
        // And the same answer as a run on its own afterwards.
        $this->assertSame($results[0], trim($this->worker(['run-reports', ...$this->period(), (string) microtime(true)])));
    }

    #[Test]
    public function reports_racing_recruitment_writes_never_block_or_break_them(): void
    {
        $vacancy = $this->fixture();
        $accepted = trim($this->worker(['accepted-application', (string) $vacancy]));
        $issued = trim($this->worker(['issued-offer', (string) $vacancy]));

        $results = $this->race([
            ['run-reports', ...$this->period()],
            ['run-reports', ...$this->period()],
            ['run-reports', ...$this->period()],
            ['apply-many', (string) $vacancy, '6'],
            ['convert', $accepted],
            ['respond-offer', $issued, 'accepted'],
            ['run-reports', ...$this->period()],
        ]);

        foreach ([0, 1, 2, 6] as $i) {
            $this->assertStringStartsWith('ok:', $results[$i], "report run {$i}: {$results[$i]}");
        }
        $this->assertCount(6, json_decode($results[3], true), 'every application was created');
        $this->assertStringStartsWith('ok:', $results[4], "conversion: {$results[4]}");
        $this->assertSame('ok', $results[5], "offer response: {$results[5]}");

        $report = json_decode($this->worker(['report']), true);
        $this->assertCount(2, $report['conversions']);
        $this->assertSame(3, count(array_filter($report['offers'], fn ($o) => $o['status'] === 'accepted')));
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
