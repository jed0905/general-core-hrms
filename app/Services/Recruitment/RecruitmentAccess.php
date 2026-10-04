<?php

namespace App\Services\Recruitment;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\InterviewPanelist;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\User;
use App\Models\Vacancy;

/**
 * Which recruitment areas a user can reach, from permissions plus assignments
 * (approver, hiring manager). Drives the navigation and the dashboard gate.
 */
class RecruitmentAccess
{
    /**
     * myInterviews (assigned panelists) does not open the dashboard.
     *
     * @return array{dashboard: bool, requisitions: bool, vacancies: bool, applicants: bool, applications: bool, offers: bool, settings: bool, myInterviews: bool}
     */
    public function for(?User $user): array
    {
        if (! $user) {
            return array_fill_keys(['dashboard', 'requisitions', 'vacancies', 'applicants', 'applications', 'offers', 'settings', 'myInterviews'], false);
        }

        $flags = [
            'requisitions' => $user->can('viewAny', JobRequisition::class),
            'vacancies' => $user->can('viewAny', Vacancy::class),
            'applicants' => $user->can('viewAny', Applicant::class),
            'applications' => $user->can('viewAny', Application::class),
            'offers' => $user->can('viewAny', JobOffer::class),
            'settings' => $user->can('recruitment.config.manage'),
        ];

        return ['dashboard' => in_array(true, $flags, true)] + $flags + [
            'myInterviews' => $user->employee_id !== null && InterviewPanelist::where('employee_id', $user->employee_id)->exists(),
        ];
    }
}
