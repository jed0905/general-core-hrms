<?php

namespace App\Models;

use App\Notifications\Careers\ResetApplicantPassword;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A careers-portal login for one applicant (guard "applicant"). Separate from
 * employee/HR users: no roles, no permissions, never satisfies the "web"
 * guard. Everything the account can reach is derived from applicant_id.
 */
class ApplicantAccount extends Authenticatable
{
    use Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'applicant_id',
        'email',
        'password',
        'pending_profile',
        'email_verified_at',
        'status',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'pending_profile'];

    protected function casts(): array
    {
        return [
            'applicant_id' => 'integer',
            'password' => 'hashed',
            'pending_profile' => 'encrypted:array',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** Activated: email proven, password chosen, linked to an applicant record. */
    public function isActivated(): bool
    {
        return $this->applicant_id !== null && $this->email_verified_at !== null && $this->password !== null;
    }

    public function isUsable(): bool
    {
        return $this->isActivated() && $this->status === self::STATUS_ACTIVE;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetApplicantPassword($token));
    }
}
