<?php

namespace App\Services\Attendance\Devices\Adapters;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Contracts\DeviceAdapter;
use App\Services\Attendance\Devices\Support\ConnectionResult;
use App\Services\Attendance\Devices\Support\FetchResult;
use App\Services\Attendance\Devices\Support\UnsupportedDeviceOperation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A device the HRMS cannot talk to (e.g. a time clock whose logs are exported
 * to CSV). It exists so imported and manually entered events can name their
 * device; every communication operation is unsupported.
 */
class ManualAdapter implements DeviceAdapter
{
    public function vendor(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Other / manual import only';
    }

    public function connectionMethods(): array
    {
        return ['none' => 'No connection (CSV import / manual entry)'];
    }

    public function capabilities(AttendanceDevice $device): array
    {
        return [];
    }

    public function testConnection(AttendanceDevice $device): ConnectionResult
    {
        throw UnsupportedDeviceOperation::for('manual', 'none', 'Connection test');
    }

    public function fetchEvents(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to, ?callable $onPage = null): FetchResult
    {
        throw UnsupportedDeviceOperation::for('manual', 'none', 'Event retrieval');
    }

    public function parsePush(AttendanceDevice $device, Request $request): array
    {
        throw UnsupportedDeviceOperation::for('manual', 'none', 'Device push');
    }
}
