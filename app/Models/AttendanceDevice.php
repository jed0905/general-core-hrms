<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A biometric/time device registered in the HRMS (any vendor). Credentials are
 * encrypted and hidden from serialization; the push token is stored as a hash.
 * Vendor-specific behavior lives in its adapter (DeviceAdapterRegistry), never here.
 */
class AttendanceDevice extends Model
{
    public const VENDORS = ['hikvision', 'zkteco', 'simulator', 'manual'];

    public const STATUS_ONLINE = 'online';

    public const STATUS_OFFLINE = 'offline';

    public const STATUS_ERROR = 'error';

    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'name', 'vendor', 'model', 'serial_number', 'legal_entity_id', 'location_id', 'timezone', 'connection_method',
        'host', 'port', 'use_tls', 'verify_tls', 'username', 'password', 'push_token_hash', 'attendance_connector_id',
        'page_size', 'sync_cursor', 'capabilities', 'settings', 'simulation', 'firmware_version', 'status', 'last_seen_at',
        'last_sync_at', 'last_success_at', 'last_error', 'clock_drift_seconds', 'is_active', 'created_by',
    ];

    protected $hidden = ['username', 'password', 'push_token_hash'];

    /** Same as the column defaults, so new instances behave like reloaded ones. */
    protected $attributes = [
        'is_active' => true,
        'use_tls' => false,
        'verify_tls' => true,
        'page_size' => 30,
        'status' => self::STATUS_UNKNOWN,
    ];

    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'use_tls' => 'boolean',
            'verify_tls' => 'boolean',
            'port' => 'integer',
            'page_size' => 'integer',
            'sync_cursor' => 'array',
            'capabilities' => 'array',
            'settings' => 'array',
            'simulation' => 'array',
            'is_active' => 'boolean',
            'clock_drift_seconds' => 'integer',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AttendanceConnector::class, 'attendance_connector_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(AttendanceDeviceSyncRun::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(BiometricEnrollment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function hasCredentials(): bool
    {
        return $this->getRawOriginal('password') !== null || $this->getRawOriginal('username') !== null;
    }

    public function baseUrl(): string
    {
        $scheme = $this->use_tls ? 'https' : 'http';
        $port = $this->port ? ':'.$this->port : '';

        return "{$scheme}://{$this->host}{$port}";
    }

    /** Safe attributes for pages: never credentials or token hashes. */
    public function toSafeArray(): array
    {
        return [
            ...$this->only([
                'id', 'name', 'vendor', 'model', 'serial_number', 'legal_entity_id', 'location_id', 'timezone',
                'connection_method', 'host', 'port', 'use_tls', 'verify_tls', 'attendance_connector_id', 'page_size',
                'capabilities', 'settings', 'firmware_version', 'status', 'last_seen_at', 'last_sync_at', 'last_success_at',
                'last_error', 'clock_drift_seconds', 'is_active', 'simulation',
            ]),
            'has_credentials' => $this->hasCredentials(),
            'has_push_token' => $this->push_token_hash !== null,
        ];
    }
}
