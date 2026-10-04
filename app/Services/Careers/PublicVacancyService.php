<?php

namespace App\Services\Careers;

use App\Models\Department;
use App\Models\EmploymentStatus;
use App\Models\Location;
use App\Models\RecruitmentPortalEvent;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public face of vacancies. Only Vacancy::publiclyVisible() rows are ever
 * listed or shown, and only through present(): a fixed set of public fields
 * (never ids, hiring manager, requisition, internal notes or, unless HR opted
 * in, salary). Publishing is an explicit HR action, separate from opening.
 */
class PublicVacancyService
{
    public function __construct(protected PortalEventRecorder $events) {}

    public function paginate(array $filters, int $perPage = 12)
    {
        return $this->query($filters)
            ->latest('vacancies.published_at')
            ->latest('vacancies.id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Vacancy $v) => $this->present($v, detail: false));
    }

    /**
     * A publicly visible vacancy by slug; anything else is a plain 404, so the
     * existence of unpublished or closed vacancies is never revealed.
     */
    public function findVisible(string $slug): Vacancy
    {
        return Vacancy::publiclyVisible()->where('public_slug', $slug)->with($this->relations())->first()
            ?? throw new NotFoundHttpException;
    }

    /**
     * Filter options drawn from what is currently public only.
     */
    public function filterOptions(): array
    {
        $visible = fn (string $column) => Vacancy::publiclyVisible()->whereNotNull($column)->select($column);

        return [
            'departments' => Department::whereIn('id', $visible('department_id'))->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['value' => $d->id, 'title' => $d->name]),
            'employment_types' => EmploymentStatus::whereIn('id', $visible('employment_status_id'))->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'title' => $s->name]),
            'locations' => Location::whereIn('id', $visible('location_id'))->orderBy('city')->get(['id', 'address', 'city'])
                ->map(fn ($l) => ['value' => $l->id, 'title' => $this->locationName($l)]),
        ];
    }

    /**
     * The only representation of a vacancy that leaves the server publicly.
     */
    public function present(Vacancy $vacancy, bool $detail = true): array
    {
        $salary = null;
        if ($vacancy->show_salary_publicly && ($vacancy->salary_min !== null || $vacancy->salary_max !== null)) {
            $salary = ['min' => $vacancy->salary_min, 'max' => $vacancy->salary_max, 'currency' => $vacancy->salary_currency];
        }

        $summary = [
            'slug' => $vacancy->public_slug,
            'title' => $vacancy->title,
            'department' => $vacancy->department?->name,
            'location' => $vacancy->location ? $this->locationName($vacancy->location) : null,
            'employment_type' => $vacancy->employmentStatus?->name,
            'closing_date' => $vacancy->closing_date?->toDateString(),
            'posted_on' => $vacancy->published_at?->toDateString(),
            'is_open' => $vacancy->isPubliclyOpen(),
        ];

        return ! $detail ? $summary : $summary + [
            'openings' => (int) $vacancy->openings,
            'description' => $vacancy->description,
            'responsibilities' => $vacancy->responsibilities,
            'qualifications' => $vacancy->qualifications,
            'salary' => $salary,
        ];
    }

    /**
     * Put an external vacancy on the careers site (or update its salary display).
     * The slug is created once and kept, so links survive title changes.
     */
    public function publish(Vacancy $vacancy, User $actor, bool $showSalary = false): Vacancy
    {
        return DB::transaction(function () use ($vacancy, $actor, $showSalary) {
            $vacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();

            if (! in_array($vacancy->visibility, Vacancy::PUBLIC_VISIBILITIES, true)) {
                throw ValidationException::withMessages(['publish' => ['Only vacancies open to external candidates (visibility "External" or "Both") can be published on the careers site.']]);
            }
            if ($vacancy->isTerminal()) {
                throw ValidationException::withMessages(['publish' => ["A {$vacancy->status} vacancy can't be published."]]);
            }

            $wasPublished = $vacancy->published_at !== null;
            $vacancy->update([
                'public_slug' => $vacancy->public_slug ?? $this->uniqueSlug($vacancy->title),
                'published_at' => $vacancy->published_at ?? now(),
                'published_by' => $actor->id,
                'show_salary_publicly' => $showSalary,
            ]);
            $this->events->record(RecruitmentPortalEvent::VACANCY_PUBLISHED, ['vacancy_id' => $vacancy->id, 'user_id' => $actor->id],
                ($wasPublished ? 'Publication settings updated' : 'Published').'; salary '.($showSalary ? 'shown' : 'hidden').'.');

            return $vacancy;
        });
    }

    public function unpublish(Vacancy $vacancy, User $actor): Vacancy
    {
        return DB::transaction(function () use ($vacancy, $actor) {
            $vacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();

            if ($vacancy->published_at === null) {
                throw ValidationException::withMessages(['publish' => ['This vacancy is not published.']]);
            }

            $vacancy->update(['published_at' => null, 'published_by' => $actor->id]);
            $this->events->record(RecruitmentPortalEvent::VACANCY_UNPUBLISHED, ['vacancy_id' => $vacancy->id, 'user_id' => $actor->id]);

            return $vacancy;
        });
    }

    protected function query(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Vacancy::publiclyVisible()
            ->with($this->relations())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('vacancies.title', 'like', "%{$search}%")
                ->orWhere('vacancies.description', 'like', "%{$search}%")
                ->orWhereHas('department', fn ($d) => $d->where('name', 'like', "%{$search}%"))
                ->orWhereHas('location', fn ($l) => $l->where('city', 'like', "%{$search}%")->orWhere('address', 'like', "%{$search}%"))))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->where('vacancies.department_id', (int) $id))
            ->when($filters['employment_type'] ?? null, fn ($q, $id) => $q->where('vacancies.employment_status_id', (int) $id))
            ->when($filters['location'] ?? null, fn ($q, $id) => $q->where('vacancies.location_id', (int) $id));
    }

    protected function relations(): array
    {
        return ['department:id,name', 'location:id,address,city', 'employmentStatus:id,name'];
    }

    protected function locationName(Location $location): string
    {
        return trim(implode(', ', array_filter([$location->address, $location->city])));
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 100, '') ?: 'job';
        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Vacancy::where('public_slug', $slug)->exists());

        return $slug;
    }
}
