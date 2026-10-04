<?php

namespace Tests\Feature\Careers;

use App\Models\ApplicantAccount;
use App\Models\Vacancy;
use App\Notifications\Careers\CompleteApplicantRegistration;
use App\Services\Careers\PublicVacancyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Recruitment\RecruitmentTestCase;

/**
 * Careers portal fixtures on top of the recruitment fixtures: published
 * vacancies and candidates who registered and activated through the portal.
 */
abstract class CareersTestCase extends RecruitmentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        RateLimiter::clear('careers-auth');
    }

    protected function published(array $overrides = [], bool $showSalary = false): Vacancy
    {
        $vacancy = $this->openVacancy(array_merge(['title' => 'Software Developer', 'closing_date' => '2026-10-31', 'salary_min' => 50000, 'salary_max' => 70000, 'salary_currency' => 'PHP', 'hiring_manager_id' => $this->ramon->employee_id], $overrides));

        return app(PublicVacancyService::class)->publish($vacancy, $this->hrStaff, $showSalary)->fresh();
    }

    protected function registration(array $overrides = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'first_name' => 'Carla', 'middle_name' => null, 'last_name' => "Candidate{$n}", 'suffix' => null,
            'email' => "carla{$n}@example.test", 'phone' => '0918 700 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'address' => 'Makati City',
            'privacy_consent' => true,
        ], $overrides);
    }

    /** The URL in the "complete your registration" email. */
    protected function completionUrl(string $email): string
    {
        $account = ApplicantAccount::where('email', mb_strtolower($email))->firstOrFail();
        $url = null;
        Notification::assertSentTo($account, CompleteApplicantRegistration::class, function ($notification) use ($account, &$url) {
            $url = $notification->url($account);

            return true;
        });

        return $url;
    }

    /** Register, follow the emailed link, choose a password: a signed-in candidate. */
    protected function candidate(array $overrides = []): ApplicantAccount
    {
        $data = $this->registration($overrides);
        $this->post(route('careers.register.store'), $data)->assertSessionHasNoErrors();
        $this->post($this->completionUrl($data['email']), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])->assertSessionHasNoErrors();
        $account = ApplicantAccount::where('email', mb_strtolower($data['email']))->firstOrFail();
        Auth::guard('applicant')->logout();

        return $account;
    }

    /**
     * Sign in on the "applicant" guard. actingAs() also makes that the default
     * guard, which never happens in real requests (the default stays "web"),
     * so restore it to test exactly what production does.
     */
    protected function asCandidate(ApplicantAccount $account): static
    {
        $this->actingAs($account, 'applicant');
        Auth::shouldUse('web');

        return $this;
    }
}
