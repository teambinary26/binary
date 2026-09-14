<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\WorkflowStep;
use App\Mail\DocumentRevisionMail;
use App\Models\Application;
use App\Models\DocumentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public function __construct(
        private ApplicationService $applications,
        private NotificationService $notifications,
        private AuditService $audit,
    ) {}

    public function verifyDocument(DocumentSubmission $document, User $user, ?string $remarks = null): void
    {
        $document->loadMissing('verification');

        if ($document->verification?->status === DocumentVerificationStatus::RevisionRequested) {
            throw ValidationException::withMessages([
                'document' => 'This document was sent back for revision. Verify it after the applicant uploads a replacement.',
            ]);
        }

        $this->updateDocument($document, DocumentVerificationStatus::Verified, $user, $remarks);
        $this->audit->log('verified', 'Verified document "'.$document->requirement_name.'" for '.$document->application->application_no, $document->application, $document, $user);
    }

    public function rejectDocument(DocumentSubmission $document, User $user, string $remarks): void
    {
        $this->requireRemarks($remarks);
        $this->updateDocument($document, DocumentVerificationStatus::Rejected, $user, $remarks);
        $this->applications->changeStatus($document->application, ApplicationStatus::Incomplete, $user, $remarks);

        $this->notifications->notifyApplicant(
            $document->application,
            'Document rejected',
            'A submitted requirement for '.$document->application->application_no.' was rejected: '.$document->requirement_name.'. '.$remarks,
            'danger'
        );
    }

    public function requestRevision(DocumentSubmission $document, User $user, string $remarks): void
    {
        $this->requireRemarks($remarks);
        $document->loadMissing('application.applicant.user', 'application.program');
        $this->updateDocument($document, DocumentVerificationStatus::RevisionRequested, $user, $remarks);
        $this->applications->changeStatus($document->application, ApplicationStatus::ForRevision, $user, $remarks);

        $this->notifications->notifyApplicant(
            $document->application,
            'Document revision required',
            'Please replace "'.$document->requirement_name.'" for application '.$document->application->application_no.'. Staff remarks: '.$remarks,
            'warning'
        );

        $email = $document->application->applicant?->email
            ?: $document->application->applicant?->user?->email;

        if ($email) {
            try {
                Mail::to($email)->send(new DocumentRevisionMail($document->application, $document, $remarks));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function completeVerification(Application $application, User $user): void
    {
        if (! in_array($application->status, [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
            ApplicationStatus::ForRevision,
        ], true)) {
            throw ValidationException::withMessages([
                'application' => 'This application is not in verification.',
            ]);
        }

        if (! $application->requiredDocumentsVerified()) {
            throw ValidationException::withMessages([
                'documents' => 'Verify every required document before marking verification as done.',
            ]);
        }

        $this->applications->changeStatus(
            $application,
            ApplicationStatus::UnderEvaluation,
            $user,
            'Verification completed. Application forwarded for evaluation.'
        );
        $this->applications->assignToWorkflowStep($application, WorkflowStep::Evaluation);

        $this->notifications->notifyApplicant(
            $application,
            'Documents verified',
            'All required documents for application '.$application->application_no.' have been verified. The application is now under evaluation.',
            'success'
        );
    }

    public function markUnderVerification(Application $application, User $user): void
    {
        if ($application->status === ApplicationStatus::Submitted) {
            $this->applications->changeStatus($application, ApplicationStatus::UnderVerification, $user, 'Documents placed under verification.');
        }
    }

    private function updateDocument(
        DocumentSubmission $document,
        DocumentVerificationStatus $status,
        User $user,
        ?string $remarks,
    ): void {
        DB::transaction(function () use ($document, $status, $user, $remarks) {
            $verification = $document->verification()->firstOrCreate(
                ['document_submission_id' => $document->id],
                ['status' => DocumentVerificationStatus::Pending->value]
            );

            $verification->update([
                'status' => $status,
                'verified_by' => $user->id,
                'verified_at' => now(),
                'remarks' => $remarks,
            ]);
        });
    }

    private function requireRemarks(string $remarks): void
    {
        if (trim($remarks) === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when rejecting a document or requesting revision.',
            ]);
        }
    }
}
