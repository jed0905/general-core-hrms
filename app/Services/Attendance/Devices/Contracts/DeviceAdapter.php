<?php

namespace App\Services\Attendance\Devices\Contracts;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Support\ConnectionResult;
use App\Services\Attendance\Devices\Support\FetchResult;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * One vendor's device integration. The attendance engine only sees
 * NormalizedEvent objects; nothing vendor-specific leaves the adapter.
 * An operation the device/protocol doesn't support throws
 * UnsupportedDeviceOperation; adapters never fabricate a successful response.
 */
interface DeviceAdapter
{
    public const CAP_TEST_CONNECTION = 'test_connection';

    public const CAP_DEVICE_INFO = 'device_info';

    public const CAP_FETCH_EVENTS = 'fetch_events';

    public const CAP_INCREMENTAL = 'incremental_sync';

    public const CAP_PUSH = 'push_events';

    public const CAP_CLOCK_CHECK = 'clock_check';

    public function vendor(): string;

    public function label(): string;

    /** @return array<string, string> connection method => label */
    public function connectionMethods(): array;

    /** @return array<int, string> capabilities for this device's connection method */
    public function capabilities(AttendanceDevice $device): array;

    public function testConnection(AttendanceDevice $device): ConnectionResult;

    /**
     * Events between two instants (UTC), following pagination internally.
     * With $onPage, each page's events are passed to it as they arrive and the
     * returned FetchResult carries no events (memory stays flat for large
     * windows); without it, all events are returned at once.
     *
     * @param  (callable(array<int, NormalizedEvent>): void)|null  $onPage
     */
    public function fetchEvents(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to, ?callable $onPage = null): FetchResult;

    /**
     * Events from a device-initiated HTTP request (already authenticated by the caller).
     *
     * @return array<int, NormalizedEvent>
     */
    public function parsePush(AttendanceDevice $device, Request $request): array;
}
