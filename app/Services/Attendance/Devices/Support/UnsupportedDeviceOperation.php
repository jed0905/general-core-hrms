<?php

namespace App\Services\Attendance\Devices\Support;

use RuntimeException;

/** The device or its connection method does not support this operation. */
class UnsupportedDeviceOperation extends RuntimeException
{
    public static function for(string $vendor, string $method, string $operation): self
    {
        return new self("{$operation} is not supported for {$vendor} devices connected by {$method}.");
    }
}
