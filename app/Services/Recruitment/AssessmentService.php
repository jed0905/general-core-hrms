<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\AssessmentType;
use App\Models\Employee;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Models\VacancyStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recruitment assessments. Status rules: scheduled → in_progress → completed;
 * scheduled/in_progress → cancelled. Completed and cancelled are final (no
 * correction workflow in this phase). The application row is locked first,
 * the same as ApplicationPipelineService, so "Assessment → Evaluation" can
 * never race with a new or reopened assessment. Never changes the stage.
 */
class AssessmentService
{
    public function __construct(protected ApplicationPipelineService $pipeline) {}

    /**
     * @param  array{assessment_type_id: int, scheduled_at?: ?string, assessor_employee_id?: ?int, remarks?: ?string}  $data
     */
    public function create(Application $application, array $data, User $actor): ApplicationAssessment
    {
        return DB::transaction(function () use ($application, $data, $actor) {
            $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->isTerminal()) {
                throw ValidationException::withMessages(['status' => ["This application is {$application->status}; no assessments can be added."]]);
            }
            if (VacancyStage::whereKey($application->current_vacancy_stage_id)->value('stage_type') !== RecruitmentStage::TYPE_ASSESSMENT) {
                throw ValidationException::withMessages(['status' => ['Move the application to the Assessment stage before adding assessments.']]);
            }
            $this->pipeline->assertVacancyAcceptsProgress($application);

            $type = AssessmentType::whereKey($data['assessment_type_id'])->where('is_active', true)->first();
            if (! $type) {
                throw ValidationException::withMessages(['assessment_type_id' => ['Choose an active assessment type.']]);
            }
            $this->assertAssessor($data['assessor_employee_id'] ?? null);

            $assessment = ApplicationAssessment::create([
                'application_id' => $application->id,
                'assessment_type_id' => $type->id,
                'result_type' => $type->result_type,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => ApplicationAssessment::STATUS_SCHEDULED,
                'assessor_employee_id' => $data['assessor_employee_id'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->id,
            ]);
            $application->update(['last_activity_at' => now()]);

            return $assessment;
        });
    }

    /**
     * Schedule, assessor and remarks of an open assessment.
     */
    public function update(ApplicationAssessment $assessment, array $data, User $actor): ApplicationAssessment
    {
        return DB::transaction(function () use ($assessment, $data, $actor) {
            [, $assessment] = $this->lockOpen($assessment, requireLiveApplication: true);
            $this->assertAssessor($data['assessor_employee_id'] ?? null);

            $assessment->update([
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'assessor_employee_id' => $data['assessor_employee_id'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => $actor->id,
            ]);

            return $assessment;
        });
    }

    public function start(ApplicationAssessment $assessment, User $actor): ApplicationAssessment
    {
        return DB::transaction(function () use ($assessment, $actor) {
            [, $assessment] = $this->lockOpen($assessment, requireLiveApplication: true);

            if ($assessment->status !== ApplicationAssessment::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['status' => ["This assessment is already {$assessment->status}."]]);
            }

            $assessment->update(['status' => ApplicationAssessment::STATUS_IN_PROGRESS, 'started_at' => now(), 'updated_by' => $actor->id]);

            return $assessment;
        });
    }

    /**
     * Record the result. A "score" assessment needs a score and maximum; a
     * "pass_fail" one needs the pass/fail outcome (a score is optional).
     *
     * @param  array{score?: ?numeric, maximum_score?: ?numeric, passed?: ?bool, remarks?: ?string}  $data
     */
    public function complete(ApplicationAssessment $assessment, array $data, User $actor): ApplicationAssessment
    {
        return DB::transaction(function () use ($assessment, $data, $actor) {
            [$application, $assessment] = $this->lockOpen($assessment, requireLiveApplication: true);

            $score = $data['score'] ?? null;
            $maximum = $data['maximum_score'] ?? null;
            $passed = isset($data['passed']) ? (bool) $data['passed'] : null;

            if ($assessment->result_type === AssessmentType::RESULT_SCORE && ($score === null || $maximum === null)) {
                throw ValidationException::withMessages(['score' => ['Enter the score and the maximum score.']]);
            }
            if ($assessment->result_type === AssessmentType::RESULT_PASS_FAIL && $passed === null) {
                throw ValidationException::withMessages(['passed' => ['Record whether the applicant passed.']]);
            }
            if (($score === null) !== ($maximum === null)) {
                throw ValidationException::withMessages(['maximum_score' => ['Enter both the score and the maximum score, or neither.']]);
            }
            if ($score !== null && (float) $score > (float) $maximum) {
                throw ValidationException::withMessages(['score' => ['The score cannot be higher than the maximum score.']]);
            }

            $assessment->update([
                'status' => ApplicationAssessment::STATUS_COMPLETED,
                'score' => $score,
                'maximum_score' => $maximum,
                'passed' => $passed,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $assessment->remarks,
                'started_at' => $assessment->started_at ?? now(),
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $application->update(['last_activity_at' => now()]);

            return $assessment;
        });
    }

    /**
     * Allowed after the application was rejected or withdrawn too.
     */
    public function cancel(ApplicationAssessment $assessment, ?string $reason, User $actor): ApplicationAssessment
    {
        return DB::transaction(function () use ($assessment, $reason, $actor) {
            [, $assessment] = $this->lockOpen($assessment, requireLiveApplication: false);

            $assessment->update([
                'status' => ApplicationAssessment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            return $assessment;
        });
    }

    /**
     * @return array{0: Application, 1: ApplicationAssessment}
     */
    protected function lockOpen(ApplicationAssessment $assessment, bool $requireLiveApplication): array
    {
        $application = Application::whereKey($assessment->application_id)->lockForUpdate()->firstOrFail();
        $assessment = ApplicationAssessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();

        if (! $assessment->isOpen()) {
            throw ValidationException::withMessages(['status' => ["This assessment is already {$assessment->status}."]]);
        }
        if ($requireLiveApplication && $application->isTerminal()) {
            throw ValidationException::withMessages(['status' => ["This application is {$application->status}; the assessment can only be cancelled."]]);
        }

        return [$application, $assessment];
    }

    protected function assertAssessor(mixed $employeeId): void
    {
        if ($employeeId && ! Employee::whereKey($employeeId)->whereNotIn('status', ['archived', 'terminated'])->exists()) {
            throw ValidationException::withMessages(['assessor_employee_id' => ['Choose a current employee as assessor.']]);
        }
    }
}
