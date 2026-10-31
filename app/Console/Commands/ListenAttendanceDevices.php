<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Streaming\HikvisionAlertStream;
use App\Services\Attendance\Devices\Support\DeviceCommunicationException;
use App\Services\Attendance\DeviceStreamService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Keeps a device's event stream open and ingests attendance events as they
 * happen (primary path); scheduled polling stays the recovery path. Reconnects
 * with bounded exponential backoff. Stops cleanly on Ctrl-C / SIGTERM.
 * Output (also appended to storage/logs/attendance-stream.log) holds connection
 * state, counts and credential-free errors only, never payloads.
 */
class ListenAttendanceDevices extends Command
{
    protected $signature = 'attendance:listen
        {--device= : Device id (required when more than one device can stream)}
        {--max-backoff=60 : Longest wait between reconnection attempts, in seconds}
        {--idle=90 : Reconnect when the device sends nothing for this many seconds}
        {--status : Show the last recorded listener state and exit}';

    protected $description = 'Listen to a biometric device event stream (Hikvision alertStream) and ingest attendance events in near real time';

    private bool $stopping = false;

    public function handle(DeviceStreamService $streams, HikvisionAlertStream $client): int
    {
        $devices = AttendanceDevice::query()->when($this->option('device'), fn ($q, $id) => $q->whereKey($id))->get()->filter(fn ($d) => $streams->supports($d))->values();

        if ($this->option('status')) {
            foreach ($devices as $device) {
                $this->line("{$device->name} (#{$device->id}): ".json_encode($streams->state($device) ?? ['state' => 'never started']));
            }

            return self::SUCCESS;
        }

        if ($devices->count() !== 1) {
            $this->error($devices->isEmpty() ? 'No active Hikvision device polled over ISAPI matches.' : 'More than one device can stream; choose one with --device (one listener per device).');

            return self::FAILURE;
        }

        $device = $devices->first();
        $this->trapSignals();
        $backoff = 1;
        $maxBackoff = max(1, (int) $this->option('max-backoff'));

        while (! $this->stopping) {
            $device->refresh();
            $counts = array_fill_keys(DeviceStreamService::OUTCOMES, 0);
            $this->log("{$device->name}: connecting to {$device->host}");
            $streams->recordState($device, ['state' => 'connecting', 'pid' => getmypid()]);

            try {
                $stats = $client->listen(
                    $device,
                    function (array $notification) use ($device, $streams, &$counts, &$backoff) {
                        $backoff = 1;
                        try {
                            $outcome = $streams->handle($device, $notification);
                        } catch (Throwable $e) {
                            report($e);
                            $outcome = 'ignored';
                            $this->log("{$device->name}: could not process a notification (".class_basename($e).')', 'warn');
                        }
                        $counts[$outcome]++;
                        if (in_array($outcome, ['stored', 'unmapped', 'duplicate'], true)) {
                            $access = $notification['AccessControllerEvent'] ?? [];
                            $this->log("{$device->name}: event serial ".($access['serialNo'] ?? '?').' at '.($notification['dateTime'] ?? '?')." -> {$outcome}");
                        }
                        if (array_sum($counts) % 25 === 0) {
                            $streams->recordState($device, ['state' => 'connected', 'counts' => $counts, 'last_message_at' => now()->toIso8601String()]);
                        }
                    },
                    fn () => $this->stopping,
                    function (string $contentType) use ($device, $streams) {
                        $this->log("{$device->name}: connected ({$contentType})");
                        $streams->recordState($device, ['state' => 'connected', 'connected_at' => now()->toIso8601String()]);
                        $device->forceFill(['status' => AttendanceDevice::STATUS_ONLINE, 'last_seen_at' => now()])->save();
                    },
                    (int) $this->option('idle'),
                );

                if ($stats['stopped']) {
                    break;
                }
            } catch (DeviceCommunicationException $e) {
                $this->log("{$device->name}: {$e->errorCode} - {$e->getMessage()} ".$this->summary($counts), 'warn');
                $streams->recordState($device, ['state' => 'reconnecting', 'last_error' => $e->getMessage(), 'counts' => $counts]);
            } catch (Throwable $e) {
                report($e);
                $this->log("{$device->name}: unexpected error (".class_basename($e).'); see the application log.', 'error');
                $streams->recordState($device, ['state' => 'reconnecting', 'last_error' => class_basename($e)]);
            }

            if ($this->stopping) {
                break;
            }

            $this->log("{$device->name}: reconnecting in {$backoff}s");
            for ($waited = 0; $waited < $backoff && ! $this->stopping; $waited++) {
                sleep(1);
            }
            $backoff = min($maxBackoff, $backoff * 2);
        }

        $streams->recordState($device, ['state' => 'stopped', 'pid' => null]);
        $this->log("{$device->name}: listener stopped");

        return self::SUCCESS;
    }

    private function trapSignals(): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, function () {
                    $this->stopping = true;
                });
            }
        }
    }

    private function summary(array $counts): string
    {
        return '('.collect($counts)->filter()->map(fn ($n, $k) => "{$k} {$n}")->implode(', ').')';
    }

    private function log(string $message, string $level = 'info'): void
    {
        $line = now()->toDateTimeString().' '.$message;
        match ($level) {
            'warn' => $this->warn($line),
            'error' => $this->error($line),
            default => $this->line($line),
        };
        file_put_contents(storage_path('logs/attendance-stream.log'), $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
