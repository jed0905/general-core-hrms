<?php

namespace App\Services\Attendance\Devices;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Contracts\DeviceAdapter;
use InvalidArgumentException;

/**
 * Vendor key => adapter, from config('attendance.adapters'). Adding a vendor is
 * a new adapter class plus one config entry; the attendance engine is unchanged.
 */
class DeviceAdapterRegistry
{
    /** @var array<string, DeviceAdapter> */
    private array $adapters = [];

    /** @param  array<string, class-string<DeviceAdapter>>  $classes */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->register(app($class));
        }
    }

    public function register(DeviceAdapter $adapter): void
    {
        $this->adapters[$adapter->vendor()] = $adapter;
    }

    public function for(AttendanceDevice|string $vendor): DeviceAdapter
    {
        $key = $vendor instanceof AttendanceDevice ? $vendor->vendor : $vendor;

        return $this->adapters[$key] ?? throw new InvalidArgumentException("No device adapter is registered for vendor {$key}.");
    }

    /** @return array<int, array{vendor: string, label: string, connection_methods: array<string, string>}> */
    public function options(): array
    {
        return collect($this->adapters)->map(fn (DeviceAdapter $a) => [
            'vendor' => $a->vendor(),
            'label' => $a->label(),
            'connection_methods' => $a->connectionMethods(),
        ])->values()->all();
    }
}
