<?php

namespace App\Services\Attendance;

use App\Models\AttendanceConnector;
use App\Models\AttendanceDevice;
use App\Models\User;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Device and connector administration. Connection settings and credentials
 * are separate operations (attendance.device.configure). Push tokens and
 * connector secrets are generated here and shown once; only hashes (tokens) or
 * encrypted values (secrets) are stored. Every change is audited without secrets.
 */
class AttendanceDeviceService
{
    public const DETAIL_FIELDS = ['name', 'vendor', 'model', 'serial_number', 'legal_entity_id', 'location_id', 'timezone', 'is_active'];

    public const CONNECTION_FIELDS = ['connection_method', 'host', 'port', 'use_tls', 'verify_tls', 'attendance_connector_id', 'page_size', 'settings', 'simulation'];

    public function __construct(
        protected DeviceAdapterRegistry $adapters,
        protected AttendanceAuditLogger $audit,
    ) {}

    public function getPaginatedDevices(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return AttendanceDevice::query()
            ->with(['location:id,city,address', 'legalEntity:id,code,name'])
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active')])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('serial_number', 'like', "%{$s}%")))
            ->when($filters['vendor'] ?? null, fn ($q, $v) => $q->where('vendor', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AttendanceDevice $d) => [...$d->toSafeArray(), 'location' => $d->location, 'legal_entity' => $d->legalEntity, 'active_enrollments_count' => $d->active_enrollments_count]);
    }

    public function create(array $details, array $connection, User $user): AttendanceDevice
    {
        $this->assertMethod($details['vendor'], $connection['connection_method']);

        return DB::transaction(function () use ($details, $connection, $user) {
            $device = new AttendanceDevice([...$details, ...$connection]);
            $this->assertConnectorScope($device);
            $device->created_by = $user->id;
            $device->save();
            $device->forceFill(['capabilities' => $this->adapters->for($device)->capabilities($device)])->save();
            $this->audit->record($device, 'created', $user, $this->audit->pendingChanges($device));

            return $device;
        });
    }

    public function updateDetails(AttendanceDevice $device, array $details, User $user): AttendanceDevice
    {
        unset($details['vendor']); // a device's vendor never changes; register a new device instead
        $device->fill($details);
        $this->assertConnectorScope($device);
        $changes = $this->audit->pendingChanges($device);
        $device->save();
        $this->audit->record($device, 'updated', $user, $changes);

        return $device;
    }

    public function updateConnection(AttendanceDevice $device, array $connection, User $user): AttendanceDevice
    {
        $this->assertMethod($device->vendor, $connection['connection_method']);

        $device->fill($connection);
        foreach (['username', 'password'] as $secret) {
            if (array_key_exists($secret, $connection) && $connection[$secret] !== null && $connection[$secret] !== '') {
                $device->{$secret} = $connection[$secret]; // blank keeps the stored value
            }
        }
        if (! empty($connection['clear_credentials'])) {
            $device->username = null;
            $device->password = null;
        }
        $this->assertConnectorScope($device);
        $device->capabilities = $this->adapters->for($device)->capabilities($device);

        $changes = $this->audit->pendingChanges($device);
        $device->save();
        $this->audit->record($device, 'connection_updated', $user, $changes);

        return $device;
    }

    /** A new push token; the plain value is returned once and only its hash is stored. */
    public function rotatePushToken(AttendanceDevice $device, User $user): string
    {
        $token = Str::random(48);
        $device->forceFill(['push_token_hash' => hash('sha256', $token)])->save();
        $this->audit->record($device, 'push_token_rotated', $user);

        return $token;
    }

    public function setActive(AttendanceDevice $device, bool $active, User $user): void
    {
        $device->forceFill(['is_active' => $active])->save();
        $this->audit->record($device, $active ? 'activated' : 'deactivated', $user);
    }

    /** @return array{0: AttendanceConnector, 1: string} connector and its secret (shown once) */
    public function createConnector(array $data, User $user): array
    {
        $secret = Str::random(64);
        $connector = AttendanceConnector::create([...$data, 'key' => 'conn_'.Str::lower(Str::random(32)), 'secret' => $secret, 'created_by' => $user->id]);
        $this->audit->record($connector, 'created', $user, ['name' => $connector->name]);

        return [$connector, $secret];
    }

    public function rotateConnectorSecret(AttendanceConnector $connector, User $user): string
    {
        $secret = Str::random(64);
        $connector->forceFill(['secret' => $secret])->save();
        $this->audit->record($connector, 'secret_rotated', $user);

        return $secret;
    }

    public function setConnectorActive(AttendanceConnector $connector, bool $active, User $user): void
    {
        $connector->forceFill(['is_active' => $active])->save();
        $this->audit->record($connector, $active ? 'activated' : 'deactivated', $user);
    }

    /**
     * A connector-delivered device needs a connector, and a connector tied to a
     * legal entity or location may only deliver for devices of that entity or
     * location, so one site's connector can never submit another's attendance.
     */
    private function assertConnectorScope(AttendanceDevice $device): void
    {
        if ($device->connection_method !== 'connector') {
            return;
        }

        $connector = $device->attendance_connector_id ? AttendanceConnector::find($device->attendance_connector_id) : null;
        if (! $connector) {
            throw ValidationException::withMessages(['attendance_connector_id' => 'Choose the connector that delivers this device\'s events.']);
        }

        foreach (['legal_entity_id' => 'legal entity', 'location_id' => 'location'] as $column => $label) {
            if ($connector->{$column} !== null && (int) $connector->{$column} !== (int) $device->{$column}) {
                throw ValidationException::withMessages(['attendance_connector_id' => "This connector serves a different {$label} than the device."]);
            }
        }
    }

    private function assertMethod(string $vendor, string $method): void
    {
        if (! array_key_exists($method, $this->adapters->for($vendor)->connectionMethods())) {
            throw ValidationException::withMessages(['connection_method' => 'This connection method is not available for the selected vendor.']);
        }
    }
}
