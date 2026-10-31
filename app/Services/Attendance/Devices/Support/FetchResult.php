<?php

namespace App\Services\Attendance\Devices\Support;

/**
 * Events read from a device for a window. $complete is false when the device
 * stopped paginating early (the next sync re-reads the overlap window).
 */
final class FetchResult
{
    /** @param  array<int, NormalizedEvent>  $events */
    public function __construct(
        public readonly array $events,
        public readonly bool $complete = true,
        public readonly int $pages = 1,
    ) {}
}
