<?php

use App\Services\Attendance\Devices\Adapters\HikvisionIsapiAdapter;
use App\Services\Attendance\Devices\Adapters\ManualAdapter;
use App\Services\Attendance\Devices\Adapters\SimulatorAdapter;
use App\Services\Attendance\Devices\Adapters\ZktecoAdmsAdapter;

return [

    /*
    |--------------------------------------------------------------------------
    | Device adapters
    |--------------------------------------------------------------------------
    |
    | Vendor key => adapter class implementing
    | App\Services\Attendance\Devices\Contracts\DeviceAdapter.
    |
    */

    'adapters' => [
        'hikvision' => HikvisionIsapiAdapter::class,
        'zkteco' => ZktecoAdmsAdapter::class,
        'simulator' => SimulatorAdapter::class,
        'manual' => ManualAdapter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    |
    | overlap_minutes: each pull starts this long before the end of the last
    | completed pull (plus the device's measured clock drift), so events near
    | the boundary or stamped by a slightly slow clock are re-read; duplicates
    | are ignored by ingestion. A failed pull doesn't advance the cursor, so
    | events recorded while a device was unreachable are read once it returns.
    | Events a device stores later than their own timestamp are picked up by
    | the daily `attendance:sync-devices --lookback=1440` pass (Console\Kernel).
    | initial_days: how far back the first pull of a new device reads.
    |
    */

    'sync' => [
        'max_attempts' => 3,
        'retry_backoff_ms' => 2000,
        'overlap_minutes' => (int) env('ATTENDANCE_SYNC_OVERLAP_MINUTES', 10),
        'initial_days' => (int) env('ATTENDANCE_SYNC_INITIAL_DAYS', 7),
        // A device whose HTTP Listening heartbeat (every 30 s) or pushed event arrived within this many
        // seconds has a healthy push channel; scheduled pulls then only reconcile every
        // push_reconcile_minutes. Without a healthy push channel a device is pulled every run.
        'push_healthy_seconds' => (int) env('ATTENDANCE_PUSH_HEALTHY_SECONDS', 120),
        'push_reconcile_minutes' => (int) env('ATTENDANCE_PUSH_RECONCILE_MINUTES', 60),
        // Daily re-read of the last 24 hours for events a device stores later than their own time.
        'lookback_daily_at' => env('ATTENDANCE_LOOKBACK_DAILY_AT', '02:30'),
        'http_timeout_seconds' => 15,
        'lock_seconds' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Device-initiated requests
    |--------------------------------------------------------------------------
    |
    | Connector batches must be signed within this many seconds of the server
    | clock, and each nonce is accepted once (replay protection).
    |
    */

    'push' => [
        'replay_window_seconds' => 300,
    ],

];
