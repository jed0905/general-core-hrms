<?php

namespace App\Services\Attendance\Devices\Support;

use Carbon\CarbonImmutable;

/**
 * A device event in vendor-neutral form. occurredAt is UTC, or null when the
 * device timestamp could not be parsed (the event is stored as invalid, never
 * dropped). payload is a sanitized subset of the device record: identifiers and
 * codes only, never biometric templates, photos or credentials.
 */
final class NormalizedEvent
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public readonly ?string $deviceUserId,
        public readonly ?string $deviceEventId,
        public readonly string $eventTimeRaw,
        public readonly ?CarbonImmutable $occurredAt,
        public readonly string $eventType = 'unknown',
        public readonly ?string $verifyMode = null,
        public readonly array $payload = [],
        public readonly ?string $invalidReason = null,
    ) {}
}
