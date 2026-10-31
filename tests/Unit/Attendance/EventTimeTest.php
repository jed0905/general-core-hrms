<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\Devices\Support\EventTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Device timestamps -> UTC: an explicit offset wins (Hikvision ISO 8601), a naive
 * time is read in the device timezone (ZKTeco ATTLOG), junk is rejected.
 */
class EventTimeTest extends TestCase
{
    protected function setUp(): void
    {
        CarbonImmutable::setTestNow('2026-11-10 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public static function cases(): array
    {
        return [
            'iso with offset ignores device tz' => ['2026-10-12T08:00:00+08:00', 'America/New_York', '2026-10-12T00:00:00+00:00'],
            'zulu' => ['2026-10-12T00:00:00Z', 'Asia/Manila', '2026-10-12T00:00:00+00:00'],
            'naive local time uses device tz' => ['2026-10-12 08:00:00', 'Asia/Manila', '2026-10-12T00:00:00+00:00'],
            'naive time during DST' => ['2026-07-01 08:00:00', 'America/New_York', '2026-07-01T12:00:00+00:00'],
            'naive time after DST ends' => ['2026-11-02 08:00:00', 'America/New_York', '2026-11-02T13:00:00+00:00'],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function device_times_are_normalized_to_utc(string $raw, string $timezone, string $expected): void
    {
        $this->assertSame($expected, EventTime::toUtc($raw, $timezone)?->toIso8601String());
    }

    #[Test]
    public function unparseable_unset_or_future_clocks_are_rejected(): void
    {
        $this->assertNull(EventTime::toUtc('', 'UTC'));
        $this->assertNull(EventTime::toUtc('garbage', 'UTC'));
        $this->assertNull(EventTime::toUtc('1970-01-01 00:00:00', 'UTC'));   // unset device clock
        $this->assertNull(EventTime::toUtc('2027-01-01 00:00:00', 'UTC'));   // far future (more than 2 days ahead)
    }
}
