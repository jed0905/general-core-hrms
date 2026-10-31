<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Contracts\DeviceAdapter;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\DeviceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Scheduled pull synchronization of every active device whose connection
 * method supports pulling. Push-only and connector devices are skipped (they
 * deliver their own events). One device's failure never stops the others.
 *
 * A scheduled run pulls a device only when DeviceSyncService::pullReason()
 * says so: devices with a working HTTP Listening push channel get a periodic
 * catch-up pull (reconciliation), others continue from their cursor every run.
 * --force pulls every device regardless (administrator-requested recovery).
 *
 * --from/--to (with --device) pull an explicit window instead of the cursor or
 * the initial look-back, e.g. a short test range on a device that already
 * holds a large event history. Times without an offset are in the device's
 * timezone. --lookback=<minutes> re-reads the last N minutes on every device
 * (the daily pass for events a device stores late).
 */
class SyncAttendanceDevices extends Command
{
    protected $signature = 'attendance:sync-devices
        {--device= : Only this device id}
        {--from= : Window start (ISO 8601; requires --device)}
        {--to= : Window end (ISO 8601; default now; requires --device)}
        {--lookback= : Re-read the last N minutes instead of continuing from the cursor}
        {--force : Pull every selected device now, even when its push channel is healthy}';

    protected $description = 'Pull attendance events from active biometric devices';

    public function handle(DeviceSyncService $sync, DeviceAdapterRegistry $adapters): int
    {
        if (($this->option('from') || $this->option('to')) && ! $this->option('device')) {
            $this->error('--from/--to need --device.');

            return self::FAILURE;
        }

        $devices = AttendanceDevice::where('is_active', true)
            ->when($this->option('device'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn (AttendanceDevice $d) => in_array(DeviceAdapter::CAP_FETCH_EVENTS, $adapters->for($d)->capabilities($d), true));

        $explicit = $this->option('force') || $this->option('from') || $this->option('lookback') || $this->option('device');
        $skipped = [];

        foreach ($devices as $device) {
            if (! $explicit && $sync->pullReason($device) === null) {
                $skipped[] = $device->name;

                continue; // push channel healthy and reconciliation not due
            }

            try {
                [$from, $to] = $this->window($device);
                $run = $sync->sync($device, $this->option('from') ? 'manual' : 'scheduled', null, $from, $to);
                $this->line(now()->toDateTimeString()." {$device->name}: {$run->status} ({$run->fetched} retrieved, {$run->inserted} new, {$run->duplicates} duplicates, {$run->unmatched} unmapped)"
                    .($run->window ? " window {$run->window['from']} .. {$run->window['to']}" : '')
                    .($run->error_message ? " - {$run->error_message}" : ''));
            } catch (ValidationException $e) {
                $this->warn(now()->toDateTimeString()." {$device->name}: ".collect($e->errors())->flatten()->first());
            } catch (Throwable $e) {
                report($e);
                $this->error(now()->toDateTimeString()." {$device->name}: failed (".class_basename($e).'); see the application log.');
            }
        }

        if ($skipped !== [] && $this->output->isVerbose()) {
            $this->line(now()->toDateTimeString().' skipped (push channel healthy): '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private function window(AttendanceDevice $device): array
    {
        if ($this->option('lookback')) {
            return [CarbonImmutable::now()->subMinutes(max(1, (int) $this->option('lookback'))), CarbonImmutable::now()];
        }

        $parse = fn (?string $value) => $value ? CarbonImmutable::parse($value, $device->timezone)->utc() : null;
        $from = $parse($this->option('from'));
        $to = $parse($this->option('to')) ?? ($from ? CarbonImmutable::now() : null);

        if ($from && $to && $from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => '--from must be before --to.']);
        }

        return [$from, $to];
    }
}
