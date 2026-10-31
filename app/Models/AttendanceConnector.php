<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An HRMS-owned on-premises connector that polls LAN devices and posts signed
 * batches to the HRMS. The HMAC secret is encrypted and never serialized.
 */
class AttendanceConnector extends Model
{
    protected $fillable = ['name', 'key', 'secret', 'legal_entity_id', 'location_id', 'is_active', 'last_seen_at', 'last_seen_ip', 'created_by'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(AttendanceDevice::class);
    }
}
