<?php

namespace App\Services\Careers;

use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\RecruitmentPortalEvent;
use App\Models\RecruitmentSource;
use App\Notifications\Careers\CompleteApplicantRegistration;
use App\Services\Recruitment\ApplicantService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Careers-portal accounts.
 *
 * 1. register(): stores the profile (encrypted) on an unactivated account and
 *    emails a signed link. The response never says whether the email is known.
 * 2. complete(): the link proves the address; the candidate chooses a password,
 *    and only then is the account linked to the applicant with that email (an
 *    HR-created record) or a new applicant is created through ApplicantService.
 *    Records are never merged; a possible duplicate by phone is sent to HR.
 */
class ApplicantAccountService
{
    public function __construct(
        protected ApplicantService $applicants,
        protected PortalEventRecorder $events
    ) {}

    /**
     * @param  array{first_name: string, middle_name?: ?string, last_name: string, suffix?: ?string, email: string, phone?: ?string, address?: ?string}  $profile
     */
    public function register(array $profile): void
    {
        $email = ApplicantService::normalizeEmail($profile['email']);
        $profile = ['email' => $email] + array_intersect_key($profile, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'phone', 'address']));

        $account = DB::transaction(function () use ($email, $profile) {
            $account = ApplicantAccount::where('email', $email)->lockForUpdate()->first();

            if ($account?->isActivated()) {
                return null; // Already registered: say nothing different; they can sign in or reset the password.
            }

            $account ??= new ApplicantAccount(['email' => $email, 'status' => ApplicantAccount::STATUS_ACTIVE]);
            $account->pending_profile = $profile + ['privacy_consented_at' => now()->toIso8601String()];
            $account->save();
            $this->events->record(RecruitmentPortalEvent::ACCOUNT_REGISTERED, ['applicant_account_id' => $account->id]);

            return $account;
        });

        $account?->notify(new CompleteApplicantRegistration);
    }

    /**
     * Resend the completion link to an unactivated account (silently does nothing otherwise).
     */
    public function resend(string $email): void
    {
        $account = ApplicantAccount::where('email', ApplicantService::normalizeEmail($email))->first();

        if ($account && ! $account->isActivated() && $account->pending_profile) {
            $account->notify(new CompleteApplicantRegistration);
        }
    }

    /**
     * The signed link was followed: activate the account with the chosen password.
     */
    public function complete(ApplicantAccount $account, string $password): ApplicantAccount
    {
        $account = DB::transaction(function () use ($account, $password) {
            $account = ApplicantAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($account->isActivated()) {
                throw ValidationException::withMessages(['password' => ['This account is already set up. Please sign in.']]);
            }

            $profile = $account->pending_profile ?? [];
            $applicant = $this->resolveApplicant($account, $profile);

            $account->update([
                'applicant_id' => $applicant->id,
                'password' => $password,
                'email_verified_at' => now(),
                'pending_profile' => null,
            ]);
            $this->events->record(RecruitmentPortalEvent::ACCOUNT_ACTIVATED, ['applicant_account_id' => $account->id, 'applicant_id' => $applicant->id]);

            return $account;
        });

        Auth::guard('applicant')->login($account);
        request()->session()->regenerate();

        return $account;
    }

    /**
     * Credentials only work for activated, active accounts.
     */
    public function attempt(string $email, string $password, bool $remember): bool
    {
        $ok = Auth::guard('applicant')->attempt([
            'email' => ApplicantService::normalizeEmail($email),
            'password' => $password,
            'status' => ApplicantAccount::STATUS_ACTIVE,
            fn ($q) => $q->whereNotNull('applicant_id')->whereNotNull('email_verified_at'),
        ], $remember);

        if ($ok) {
            Auth::guard('applicant')->user()->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return $ok;
    }

    /**
     * The applicant with this email (created by HR, never linked to another
     * account), or a new applicant. Anything ambiguous goes to HR instead of
     * guessing, and the message reveals nothing about other records.
     */
    protected function resolveApplicant(ApplicantAccount $account, array $profile): Applicant
    {
        $refer = fn () => ValidationException::withMessages(['password' => ['We could not set up your account automatically. Please contact our HR team.']]);

        $matches = Applicant::where('normalized_email', $account->email)->lockForUpdate()->get();
        if ($matches->count() > 1) {
            throw $refer();
        }

        if ($existing = $matches->first()) {
            if (ApplicantAccount::where('applicant_id', $existing->id)->exists()) {
                throw $refer();
            }
            $this->events->record(RecruitmentPortalEvent::APPLICANT_LINKED, ['applicant_account_id' => $account->id, 'applicant_id' => $existing->id]);

            return $existing;
        }

        // Someone else's record shares this phone number: don't create a silent duplicate.
        if (! empty($profile['phone']) && $this->applicants->findDuplicates(['phone' => $profile['phone']])->isNotEmpty()) {
            throw $refer();
        }

        try {
            $applicant = $this->applicants->createApplicant([
                'first_name' => $profile['first_name'] ?? '',
                'middle_name' => $profile['middle_name'] ?? null,
                'last_name' => $profile['last_name'] ?? '',
                'suffix' => $profile['suffix'] ?? null,
                'email' => $account->email,
                'phone' => $profile['phone'] ?? null,
                'address' => $profile['address'] ?? null,
                'recruitment_source_id' => RecruitmentSource::where('code', 'company_website')->value('id'),
                'source_details' => 'Careers portal',
                'is_internal' => false,
            ], null);
        } catch (ValidationException) {
            throw $refer(); // the internal duplicate message names other applicants; never show it publicly
        }
        $this->events->record(RecruitmentPortalEvent::APPLICANT_CREATED, ['applicant_account_id' => $account->id, 'applicant_id' => $applicant->id]);

        return $applicant;
    }
}
