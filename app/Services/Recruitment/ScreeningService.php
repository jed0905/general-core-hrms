<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\ApplicationScreening;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Models\VacancyStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Screening results. A passed screening makes the application eligible for the
 * shortlist; a failed screening rejects it (through the pipeline service) in
 * the same transaction.
 */
class ScreeningService
{
    public function __construct(protected ApplicationPipelineService $pipeline) {}

    /**
     * Create or update the screening of an application in a screening stage.
     *
     * @param  array{result: string, remarks?: ?string, screened_on: string, rejection_reason_id?: ?int}  $data
     */
    public function record(Application $application, array $data, User $actor): ApplicationScreening
    {
        return DB::transaction(function () use ($application, $data, $actor) {
            $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->status !== Application::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['result' => ["This application is {$application->status}; its screening can no longer change."]]);
            }

            $stage = VacancyStage::findOrFail($application->current_vacancy_stage_id);
            if ($stage->stage_type !== RecruitmentStage::TYPE_SCREENING) {
                throw ValidationException::withMessages(['result' => ['Move the application to Screening before recording a screening result.']]);
            }

            $existing = ApplicationScreening::where('application_id', $application->id)->lockForUpdate()->first();
            $permission = $existing ? 'recruitment.screening.update' : 'recruitment.screening.create';
            if (! $actor->can($permission)) {
                throw ValidationException::withMessages(['result' => ['You are not allowed to record this screening.']]);
            }

            $failed = $data['result'] === ApplicationScreening::RESULT_FAILED;
            $attributes = [
                'result' => $data['result'],
                'remarks' => $data['remarks'] ?? null,
                'screened_on' => $data['screened_on'],
                'screened_by' => $actor->id,
                'rejection_reason_id' => $failed ? ($data['rejection_reason_id'] ?? null) : null,
            ];

            $screening = $existing
                ? tap($existing)->update($attributes + ['updated_by' => $actor->id])
                : ApplicationScreening::create($attributes + ['application_id' => $application->id]);

            if ($failed) {
                if (empty($data['rejection_reason_id'])) {
                    throw ValidationException::withMessages(['rejection_reason_id' => ['Choose why the application failed screening.']]);
                }
                $this->pipeline->reject($application, (int) $data['rejection_reason_id'], $actor, $data['remarks'] ?? 'Failed screening.');
            }

            return $screening;
        });
    }
}
