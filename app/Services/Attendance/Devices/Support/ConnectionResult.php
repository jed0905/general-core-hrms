<?php

namespace App\Services\Attendance\Devices\Support;

/** Outcome of a connection test, with whatever the device reported about itself. */
final class ConnectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $model = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $firmware = null,
        public readonly ?int $clockDriftSeconds = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
    ) {}
}
