<?php

namespace App\Services\Recruitment;

use App\Models\Applicant;
use App\Models\NumberSequence;
use App\Models\User;
use App\Services\NumberSequenceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applicant records: numbering, duplicate detection, profile, education and
 * work experience. Duplicates are never merged silently: a likely duplicate is
 * reported, and HR either opens the existing record or confirms it is a
 * different person.
 */
class ApplicantService
{
    public const PROFILE_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'preferred_name',
        'email', 'phone', 'alternate_phone', 'address',
        'recruitment_source_id', 'source_details', 'is_internal', 'employee_id',
    ];

    public const EDUCATION_FIELDS = ['level', 'institute', 'degree', 'major_specialization', 'start_date', 'end_date', 'is_completed', 'notes'];

    /** Same canonical names as employee work experience. */
    public const WORK_EXPERIENCE_FIELDS = ['company', 'job_title', 'from', 'to', 'notes'];

    public function __construct(protected NumberSequenceService $numberSequences) {}

    public static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * Digits only, compared on the last 10 so "+63 917 123 4567" and "0917-123-4567" match.
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits === '' ? null : substr($digits, -10);
    }

    public function getPaginatedApplicants(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Applicant::query()
            ->with('source:id,name')
            ->withCount('applications')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['source_id'] ?? null, fn ($q, $id) => $q->where('recruitment_source_id', $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $email = self::normalizeEmail($search);
                $phone = self::phoneKey($search);
                $q->where(fn ($w) => $w
                    ->where('applicant_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('normalized_email', 'like', "%{$email}%")
                    ->when($phone && strlen($phone) >= 4, fn ($p) => $p->orWhere('phone_key', 'like', "%{$phone}%")));
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Existing applicants sharing a normalized email or phone with $data.
     *
     * @return Collection<int, Applicant>
     */
    public function findDuplicates(array $data, ?int $ignoreId = null, bool $lock = false): Collection
    {
        $email = self::normalizeEmail($data['email'] ?? null);
        $phones = array_values(array_filter([self::phoneKey($data['phone'] ?? null), self::phoneKey($data['alternate_phone'] ?? null)]));

        if (! $email && ! $phones) {
            return collect();
        }

        return Applicant::query()
            ->where(function ($q) use ($email, $phones) {
                if ($email) {
                    $q->orWhere('normalized_email', $email);
                }
                if ($phones) {
                    $q->orWhereIn('phone_key', $phones)->orWhereIn('alternate_phone_key', $phones);
                }
            })
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'applicant_number', 'first_name', 'last_name', 'normalized_email', 'phone_key', 'alternate_phone_key']);
    }

    /**
     * $actor is the HR user; null when the candidate registers through the careers portal.
     */
    public function createApplicant(array $data, ?User $actor, bool $allowDuplicate = false): Applicant
    {
        return DB::transaction(function () use ($data, $actor, $allowDuplicate) {
            // Issuing the number locks the applicant sequence row, which also
            // serializes the duplicate check below across concurrent requests.
            $number = $this->numberSequences->next(
                NumberSequence::APPLICANT_NUMBER,
                fn (string $candidate) => Applicant::where('applicant_number', $candidate)->exists()
            );

            $this->guardAgainstDuplicates($data, null, $allowDuplicate);

            $applicant = Applicant::create($this->profileAttributes($data) + [
                'applicant_number' => $number,
                'privacy_consent' => true,
                'privacy_consented_at' => now(),
                'status' => Applicant::STATUS_ACTIVE,
                'created_by' => $actor?->id,
            ]);

            $this->syncEducation($applicant, $data['education'] ?? []);
            $this->syncWorkExperience($applicant, $data['work_experience'] ?? []);

            return $applicant;
        });
    }

    public function updateApplicant(Applicant $applicant, array $data, bool $allowDuplicate = false): Applicant
    {
        return DB::transaction(function () use ($applicant, $data, $allowDuplicate) {
            // Same serialization point as creation, so an edit can't race a new duplicate in.
            NumberSequence::where('key', NumberSequence::APPLICANT_NUMBER)->lockForUpdate()->first();
            $applicant = Applicant::whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            $this->guardAgainstDuplicates($data, $applicant->id, $allowDuplicate);

            $applicant->update($this->profileAttributes($data) + Arr::only($data, ['status']));

            if (array_key_exists('education', $data)) {
                $this->syncEducation($applicant, $data['education'] ?? []);
            }
            if (array_key_exists('work_experience', $data)) {
                $this->syncWorkExperience($applicant, $data['work_experience'] ?? []);
            }

            return $applicant;
        });
    }

    protected function guardAgainstDuplicates(array $data, ?int $ignoreId, bool $allowDuplicate): void
    {
        if ($allowDuplicate) {
            return;
        }

        $duplicates = $this->findDuplicates($data, $ignoreId, lock: true);

        if ($duplicates->isNotEmpty()) {
            $email = self::normalizeEmail($data['email'] ?? null);
            $list = $duplicates->map(function (Applicant $a) use ($email) {
                $match = $email && $a->normalized_email === $email ? 'same email' : 'same phone';

                return "{$a->applicant_number} ({$a->first_name} {$a->last_name}, {$match})";
            })->implode('; ');

            throw ValidationException::withMessages([
                'duplicate' => ["This may be an existing applicant: {$list}. Open that record, or confirm this is a different person."],
            ]);
        }
    }

    protected function profileAttributes(array $data): array
    {
        $attributes = Arr::only($data, self::PROFILE_FIELDS);

        if (array_key_exists('email', $attributes) || array_key_exists('phone', $attributes) || array_key_exists('alternate_phone', $attributes)) {
            $attributes['normalized_email'] = self::normalizeEmail($data['email'] ?? null);
            $attributes['phone_key'] = self::phoneKey($data['phone'] ?? null);
            $attributes['alternate_phone_key'] = self::phoneKey($data['alternate_phone'] ?? null);
        }

        if (empty($attributes['is_internal'])) {
            $attributes['employee_id'] = null;
        }

        return $attributes;
    }

    protected function syncEducation(Applicant $applicant, array $rows): void
    {
        $applicant->education()->delete();
        $rows = collect($rows)
            ->filter(fn ($row) => filled($row['institute'] ?? null))
            ->map(fn ($row) => Arr::only($row, self::EDUCATION_FIELDS) + ['is_completed' => false])
            ->values()->all();

        if ($rows) {
            $applicant->education()->createMany($rows);
        }
    }

    protected function syncWorkExperience(Applicant $applicant, array $rows): void
    {
        $applicant->workExperience()->delete();
        $rows = collect($rows)
            ->filter(fn ($row) => filled($row['company'] ?? null) || filled($row['job_title'] ?? null))
            ->map(fn ($row) => Arr::only($row, self::WORK_EXPERIENCE_FIELDS))
            ->values()->all();

        if ($rows) {
            $applicant->workExperience()->createMany($rows);
        }
    }
}
