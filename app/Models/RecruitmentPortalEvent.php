<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit of careers-portal activity (never passwords or tokens).
 */
class RecruitmentPortalEvent extends Model
{
    public const ACCOUNT_REGISTERED = 'account_registered';

    public const ACCOUNT_ACTIVATED = 'account_activated';

    public const APPLICANT_CREATED = 'applicant_created';

    public const APPLICANT_LINKED = 'applicant_linked';

    public const PROFILE_UPDATED = 'profile_updated';

    public const DOCUMENT_UPLOADED = 'document_uploaded';

    public const DOCUMENT_DELETED = 'document_deleted';

    public const APPLICATION_SUBMITTED = 'application_submitted';

    public const APPLICATION_WITHDRAWN = 'application_withdrawn';

    public const VACANCY_PUBLISHED = 'vacancy_published';

    public const VACANCY_UNPUBLISHED = 'vacancy_unpublished';

    protected $fillable = ['event', 'applicant_account_id', 'applicant_id', 'application_id', 'vacancy_id', 'user_id', 'remarks', 'ip_address', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Portal history is immutable.'));
        static::deleting(fn () => throw new LogicException('Portal history is immutable.'));
    }
}
