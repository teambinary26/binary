<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Mail\ApplicationAcceptedMail;
use App\Mail\ApplicationApprovedMail;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(
        private ApplicationService $applications,
        private NotificationService $notifications,
        private AuditService $audit,
    ) {}

    public function decide(Application $application, User $user, array $data): void
    {
        $decision = $data['decision'];

        if (in_array($decision, ['rejected', 'revision'], true) && blank($data['remarks'] ?? null)) {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when rejecting or returning an application.',
            ]);
        }

        $amount = $data['approved_amount'] ?? $application->latestEvaluation?->recommended_amount;

        $application->approvals()->create([
            'officer_id' => $user->id,
            'decision' => $decision,
            'approved_amount' => $decision === 'approved' ? $amount : null,
            'remarks' => $data['remarks'] ?? '',
            'decided_at' => now(),
        ]);

        if ($decision === 'approved') {
            $application->update(['approved_amount' => $amount]);
            $this->applications->changeStatus($application, ApplicationStatus::Approved, $user, $data['remarks'] ?? 'Application approved.', 'approved');
            $this->notifications->notifyApplicant(
                $application,
                'Application approved',
                'Application '.$application->application_no.' has been approved. Approved amount: '.peso($amount, false).'.',
                'success'
            );
            $this->issueAccountAndEmail($application);
        } elseif ($decision === 'rejected') {
            $this->applications->changeStatus($application, ApplicationStatus::Rejected, $user, $data['remarks'], 'rejected');
            $this->notifications->notifyApplicant(
                $application,
                'Application rejected',
                'Application '.$application->application_no.' was not approved. '.$data['remarks'],
                'danger'
            );
        } else {
            $this->applications->changeStatus($application, ApplicationStatus::ForRevision, $user, $data['remarks']);
            $this->notifications->notifyApplicant(
                $application,
                'Application returned for revision',
                'Application '.$application->application_no.' was returned for revision. '.$data['remarks'],
                'warning'
            );
        }
    }

    private function issueAccountAndEmail(Application $application): void
    {
        $application->loadMissing(['applicant.user', 'program']);
        $account = $application->applicant?->user;
        $password = null;

        if ($account && $account->pending_account) {
            $password = Str::password(12, symbols: false);
            $account->update([
                'password' => $password,
                'is_active' => true,
                'pending_account' => false,
            ]);
        }

        $email = $application->applicant?->email ?: $account?->email;

        if ($email) {
            try {
                Mail::to($email)->send(new ApplicationApprovedMail($application, $password));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Approve the applicant's initial submission so they can access the portal.
     * Activates the account, generates a fresh password, and emails the
     * credentials together with the program-approval confirmation.
     */
    public function approveApplicant(Application $application, User $officer, ?string $remarks = null): void
    {
        $application->loadMissing(['applicant.user', 'program']);
        $account = $application->applicant?->user;

        if (! $account) {
            throw ValidationException::withMessages([
                'application' => 'This application has no linked applicant account.',
            ]);
        }

        if ($application->status !== ApplicationStatus::Draft) {
            throw ValidationException::withMessages([
                'application' => 'This application has already been reviewed.',
            ]);
        }

        $password = Str::password(12, symbols: false);
        $loginEmail = $application->applicant?->email ?: $account->email;

        $account->update([
            'email' => $loginEmail ?: $account->email,
            'password' => $password,
            'is_active' => true,
            'pending_account' => false,
        ]);

        $note = $remarks ?: 'Applicant accepted. They may now complete the requirements.';

        $this->applications->changeStatus(
            $application,
            ApplicationStatus::Accepted,
            $officer,
            $note,
            'accepted',
        );

        $this->notifications->notifyApplicant(
            $application,
            'Application accepted',
            'Your application '.$application->application_no.' has been accepted. Please check your email for your sign-in email and temporary password.',
            'success',
        );

        if ($loginEmail) {
            try {
                Mail::to($loginEmail)->send(new ApplicationAcceptedMail($application, $loginEmail, $password));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Reject an applicant before they were given portal access.
     */
    public function rejectApplicant(Application $application, User $officer, string $remarks): void
    {
        $application->loadMissing('applicant.user');

        if (blank($remarks)) {
            throw ValidationException::withMessages([
                'remarks' => 'Please provide a reason for rejecting the application.',
            ]);
        }

        $this->applications->changeStatus(
            $application,
            ApplicationStatus::Rejected,
            $officer,
            $remarks,
            'rejected',
        );

        $this->notifications->notifyApplicant(
            $application,
            'Application rejected',
            'Application '.$application->application_no.' was not approved. '.$remarks,
            'danger',
        );
    }
}
