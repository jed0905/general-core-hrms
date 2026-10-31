<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\Devices\Support\NormalizedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The optional on-premises HRMS connector. It runs inside the customer's LAN
 * (no vendor desktop software), pulls events from the devices listed in its
 * local JSON configuration with the same vendor adapters as the HRMS, spools
 * them on disk, and delivers HMAC-signed batches to the hosted HRMS. Batches
 * that fail stay spooled and are retried; the HRMS deduplicates, so a batch
 * delivered twice is stored once. Device credentials stay in the local file
 * and are never sent to the HRMS.
 *
 * Config (JSON): {"hrms_url": "...", "connector_key": "...", "connector_secret": "...",
 *   "spool_dir": "/var/lib/hrms-connector", "devices": [{"hrms_device_id": 12, "vendor": "hikvision",
 *   "connection_method": "isapi_pull", "host": "192.168.1.20", "port": 80, "use_tls": false,
 *   "verify_tls": true, "username": "...", "password": "...", "timezone": "Asia/Manila", "page_size": 30}]}
 */
class RunAttendanceConnector extends Command
{
    protected $signature = 'attendance:connector {config : Path to the connector JSON configuration} {--once : Run one cycle and exit} {--interval=60 : Seconds between cycles}';

    protected $description = 'Run the on-premises attendance connector (polls LAN devices, delivers signed batches to the HRMS)';

    public function handle(DeviceAdapterRegistry $adapters): int
    {
        $config = json_decode((string) @file_get_contents($this->argument('config')), true);
        if (! is_array($config) || empty($config['hrms_url']) || empty($config['connector_key']) || empty($config['connector_secret'])) {
            $this->error('Invalid connector configuration.');

            return self::FAILURE;
        }
        $spool = rtrim($config['spool_dir'] ?? storage_path('app/attendance-connector'), '/');
        @mkdir($spool, 0700, true);

        do {
            foreach ($config['devices'] ?? [] as $deviceConfig) {
                $this->collect($adapters, $deviceConfig, $spool);
            }
            $this->deliver($config, $spool);

            if (! $this->option('once')) {
                sleep(max(10, (int) $this->option('interval')));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /** Pull new events from one LAN device into a spool file. */
    private function collect(DeviceAdapterRegistry $adapters, array $deviceConfig, string $spool): void
    {
        $device = new AttendanceDevice([...$deviceConfig, 'name' => 'connector device '.$deviceConfig['hrms_device_id']]);
        $stateFile = "{$spool}/state-{$deviceConfig['hrms_device_id']}.json";
        $state = json_decode((string) @file_get_contents($stateFile), true) ?: [];
        $to = CarbonImmutable::now();
        $from = isset($state['last_event_at'])
            ? CarbonImmutable::parse($state['last_event_at'])->subMinutes((int) config('attendance.sync.overlap_minutes', 1440))
            : $to->subDays((int) config('attendance.sync.initial_days', 7));

        try {
            $events = $adapters->for($device)->fetchEvents($device, $from, $to)->events;
        } catch (Throwable $e) {
            $this->warn("Device {$deviceConfig['hrms_device_id']}: ".($e instanceof RuntimeException ? $e->getMessage() : class_basename($e)));

            return;
        }

        // The overlap window re-reads recent events; spool only ones not spooled before.
        $seen = $state['seen'] ?? [];
        $key = fn (NormalizedEvent $e) => sha1(implode('|', [$e->deviceEventId, $e->deviceUserId, $e->eventTimeRaw, $e->eventType]));
        $events = array_values(array_filter($events, fn (NormalizedEvent $e) => ! isset($seen[$key($e)])));
        if ($events === []) {
            return;
        }

        $payload = array_map(fn (NormalizedEvent $e) => ['user' => $e->deviceUserId, 'id' => $e->deviceEventId, 'time' => $e->eventTimeRaw, 'type' => $e->eventType, 'verify' => $e->verifyMode], $events);
        file_put_contents("{$spool}/batch-".now()->format('YmdHisv').'-'.Str::random(6).'.json', json_encode(['device_id' => $deviceConfig['hrms_device_id'], 'events' => $payload]), LOCK_EX);

        $cutoff = $from->subDay()->getTimestamp();
        foreach ($events as $event) {
            $seen[$key($event)] = $event->occurredAt?->getTimestamp() ?? $to->getTimestamp();
        }
        $latest = collect($events)->map(fn ($e) => $e->occurredAt?->toIso8601String())->filter()->max();
        file_put_contents($stateFile, json_encode([
            'last_event_at' => max($state['last_event_at'] ?? '', (string) $latest),
            'seen' => array_filter($seen, fn ($at) => $at >= $cutoff),
        ]), LOCK_EX);
    }

    /** Send spooled batches oldest first; keep any that fail for the next cycle. */
    private function deliver(array $config, string $spool): void
    {
        foreach (glob("{$spool}/batch-*.json") ?: [] as $file) {
            $body = json_encode(['batches' => [json_decode((string) file_get_contents($file), true)]]);
            $timestamp = (string) time();
            $nonce = Str::random(32);

            try {
                $response = Http::timeout(30)->withHeaders([
                    'X-Connector-Key' => $config['connector_key'],
                    'X-Timestamp' => $timestamp,
                    'X-Nonce' => $nonce,
                    'X-Signature' => hash_hmac('sha256', $timestamp."\n".$nonce."\n".$body, $config['connector_secret']),
                ])->withBody($body, 'application/json')->post(rtrim($config['hrms_url'], '/').'/attendance/connector/events');
            } catch (Throwable) {
                $this->warn('HRMS unreachable; batches stay spooled.');

                return;
            }

            if ($response->successful()) {
                @unlink($file);
            } else {
                $this->warn("HRMS rejected a batch (HTTP {$response->status()}); it stays spooled.");

                return;
            }
        }
    }
}
