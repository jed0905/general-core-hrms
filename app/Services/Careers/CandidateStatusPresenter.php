<?php

namespace App\Services\Careers;

use App\Models\Application;
use App\Models\JobOffer;
use App\Models\RecruitmentStage;

/**
 * What a candidate may see about their own application: a coarse status by
 * stage type, never internal stage names, scores, panelists, scorecards,
 * rejection reasons, selection decisions or offer approvals. Screening and
 * the shortlist show as "Under review"; Interview, Assessment and Evaluation
 * show as themselves. A selection or an offer still being prepared stays at
 * "Evaluation" (it can still change); an offer becomes visible only once it
 * is issued.
 */
class CandidateStatusPresenter
{
    public const STEPS = ['Submitted', 'Under review', 'Interview', 'Assessment', 'Evaluation', 'Offer'];

    /**
     * @return array{key: string, label: string, description: string, step: int, closed: bool}
     */
    public function present(Application $application, ?JobOffer $latestOffer, bool $converted): array
    {
        if ($application->status === Application::STATUS_WITHDRAWN) {
            return $this->status('withdrawn', 'Application withdrawn', 'You withdrew this application.', 0, true);
        }
        if ($application->status === Application::STATUS_REJECTED) {
            return $this->status('closed', 'Application closed', 'Thank you for your interest. This application is no longer being considered.', 0, true);
        }
        if ($converted || $latestOffer?->status === JobOffer::STATUS_ACCEPTED) {
            return $this->status('offer_accepted', 'Offer accepted', 'You accepted our offer. Our HR team will contact you about your start.', 6, false);
        }
        if ($latestOffer?->status === JobOffer::STATUS_ISSUED) {
            return $this->status('offer_available', 'Offer available', 'We have made you an offer. Our HR team will contact you with the details.', 6, false);
        }
        if (in_array($latestOffer?->status, [JobOffer::STATUS_DECLINED, JobOffer::STATUS_EXPIRED], true)) {
            return $this->status('closed', 'Application closed', 'This application is no longer active.', 0, true);
        }

        return match ($application->currentStage?->stage_type) {
            RecruitmentStage::TYPE_APPLIED => $this->status('submitted', 'Application submitted', 'We received your application.', 1, false),
            RecruitmentStage::TYPE_INTERVIEW => $this->status('interview', 'Interview', 'You are invited to the interview stage. Our team will contact you to arrange it.', 3, false),
            RecruitmentStage::TYPE_ASSESSMENT => $this->status('assessment', 'Assessment', 'You are in the assessment stage. Our team will contact you with instructions.', 4, false),
            RecruitmentStage::TYPE_EVALUATION => $this->status('evaluation', 'Evaluation', 'Our team is completing the evaluation of your application.', 5, false),
            default => $this->status('under_review', 'Under review', 'Our team is reviewing your application.', 2, false),
        };
    }

    protected function status(string $key, string $label, string $description, int $step, bool $closed): array
    {
        return compact('key', 'label', 'description', 'step', 'closed');
    }
}
