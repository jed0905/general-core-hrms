<?php

namespace App\Services\Attendance\Ingestion;

/** Counters of one ingestion batch, plus the employee work dates it touched. */
final class IngestResult
{
    /** @param  array<int, array<string, true>>  $touched  employee id => [local date => true] */
    public function __construct(
        public int $fetched = 0,
        public int $inserted = 0,
        public int $duplicates = 0,
        public int $unmatched = 0,
        public int $invalid = 0,
        public array $touched = [],
        public ?string $latestOccurredAt = null,
    ) {}

    /** Add another batch's counters (used when a pull is ingested page by page). */
    public function merge(self $other): void
    {
        $this->fetched += $other->fetched;
        $this->inserted += $other->inserted;
        $this->duplicates += $other->duplicates;
        $this->unmatched += $other->unmatched;
        $this->invalid += $other->invalid;
        foreach ($other->touched as $employeeId => $dates) {
            $this->touched[$employeeId] = ($this->touched[$employeeId] ?? []) + $dates;
        }
        if ($other->latestOccurredAt !== null) {
            $this->latestOccurredAt = max($this->latestOccurredAt ?? '', $other->latestOccurredAt);
        }
    }
}
