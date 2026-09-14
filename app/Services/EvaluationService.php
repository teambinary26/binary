<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\WorkflowStep;
use App\Models\Application;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EvaluationService
{
    public function __construct(
        private ApplicationService $applications,
        private NotificationService $notifications,
        private AuditService $audit,
    ) {}

    public function evaluate(Application $application, User $user, array $data): void
    {
        if ($application->status !== ApplicationStatus::UnderEvaluation) {
            throw ValidationException::withMessages([
                'application' => 'This application is not in evaluation.',
            ]);
        }
        $recommendation = $data['recommendation'];

        if (in_array($recommendation, ['rejection', 'revision'], true) && blank($data['remarks'] ?? null)) {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when recommending rejection or returning an application for revision.',
            ]);
        }

        $application->evaluations()->create([
            'evaluator_id' => $user->id,
            'eligibility_passed' => (bool) ($data['eligibility_passed'] ?? false),
            'eligibility_checks' => $data['eligibility_checks'] ?? [],
            'documents_complete' => (bool) ($data['documents_complete'] ?? false),
            'assessment' => $data['assessment'] ?? null,
            'recommendation' => $recommendation,
            'recommended_amount' => $data['recommended_amount'] ?? null,
            'remarks' => $data['remarks'],
            'evaluated_at' => now(),
        ]);

        $this->audit->log('updated', 'Evaluated application '.$application->application_no.' with recommendation: '.$recommendation, $application, $application, $user);

        if ($recommendation === 'revision') {
            $this->applications->changeStatus($application, ApplicationStatus::ForRevision, $user, $data['remarks']);
            $this->notifications->notifyApplicant(
                $application,
                'Application returned for revision',
                'Application '.$application->application_no.' was returned for revision. '.$data['remarks'],
                'warning'
            );

            return;
        }

        $application->unsetRelation('latestEvaluation');
        $application->load('latestEvaluation');
        $this->forwardToApproval($application, $user);
    }

    public function forwardToApproval(Application $application, User $user): void
    {
        $application->loadMissing('latestEvaluation');

        if ($application->status !== ApplicationStatus::UnderEvaluation) {
            throw ValidationException::withMessages([
                'application' => 'This application is not in evaluation.',
            ]);
        }

        if (! $application->latestEvaluation || ! in_array($application->latestEvaluation->recommendation, ['approval', 'rejection'], true)) {
            throw ValidationException::withMessages([
                'evaluation' => 'Record a complete evaluation before sending this application for approval.',
            ]);
        }

        $this->applications->changeStatus(
            $application,
            ApplicationStatus::ForApproval,
            $user,
            'Evaluation completed. Application forwarded for approval.'
        );
        $this->applications->assignToWorkflowStep($application, WorkflowStep::Approval);

        $this->notifications->notifyApplicant(
            $application,
            'Application evaluated',
            'Application '.$application->application_no.' has been evaluated and is waiting for a final approval decision.',
            'info'
        );
    }
}
