<?php

namespace App\Services\Attendance\Devices\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Device timestamps: a string with an explicit offset (ISO 8601, as Hikvision
 * sends) keeps that offset; a naive local time (as ZKTeco sends) is read in the
 * device's configured timezone. Results are UTC. Unparseable or implausible
 * values return null so the event is stored as invalid.
 */
final class EventTime
{
    public static function toUtc(string $raw, string $deviceTimezone): ?CarbonImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $raw);
            $time = $hasOffset ? CarbonImmutable::parse($raw) : CarbonImmutable::parse($raw, $deviceTimezone);
        } catch (Throwable) {
            return null;
        }

        // Reject device clocks that are clearly wrong (unset RTC, far future).
        if ($time->year < 2000 || $time->greaterThan(CarbonImmutable::now()->addDays(2))) {
            return null;
        }

        return $time->utc();
    }
}
