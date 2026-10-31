<?php

namespace App\Services\Attendance\Devices\Support;

use RuntimeException;
use Throwable;

/**
 * A device request failed. The message is safe to show and store: adapters
 * never put credentials, tokens or raw payloads in it.
 */
class DeviceCommunicationException extends RuntimeException
{
    public const CONNECTION_FAILED = 'connection_failed';

    public const AUTH_FAILED = 'auth_failed';

    public const TIMEOUT = 'timeout';

    public const BAD_RESPONSE = 'bad_response';

    public const DEVICE_ERROR = 'device_error';

    /** Errors worth retrying (transient network conditions). */
    public const RETRYABLE = [self::CONNECTION_FAILED, self::TIMEOUT];

    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    /** Transport failures (DNS, refused, TLS, timeout) as safe errors without URLs or credentials. */
    public static function fromTransport(Throwable $e): self
    {
        return str_contains(strtolower($e->getMessage()), 'timed out')
            ? new self(self::TIMEOUT, 'The device did not respond in time.')
            : new self(self::CONNECTION_FAILED, 'The device could not be reached.');
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE, true);
    }
}
