<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\WorkflowStep;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\AssistanceProgram;
use App\Models\DocumentSubmission;
use App\Models\DocumentVerification;
use App\Models\ProgramFormField;
use App\Models\User;
use App\Models\WorkflowStaff;
use App\Support\DocumentFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    public function __construct(
        private AuditService $audit,
        private NotificationService $notifications,
        private DocumentOcrService $ocr,
    ) {}

    public function generateNumber(string $prefix, string $table, string $column): string
    {
        $year = now()->year;
        $like = $prefix.'-'.$year.'-%';

        $latest = DB::table($table)
            ->where($column, 'like', $like)
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%d-%06d', $prefix, $year, $sequence);
    }

    public function start(Applicant $applicant, AssistanceProgram $program): Application
    {
        $existing = Application::query()
            ->where('applicant_id', $applicant->id)
            ->where('assistance_program_id', $program->id)
            ->whereIn('status', [
                ApplicationStatus::Draft->value,
                ApplicationStatus::Accepted->value,
                ApplicationStatus::ForRevision->value,
                ApplicationStatus::Incomplete->value,
            ])->first();

        if ($existing) {
            return $existing;
        }

        $current = $applicant->currentApplication();
        if ($current && $current->assistance_program_id !== $program->id) {
            throw ValidationException::withMessages([
                'program' => 'You already have a current application for '.($current->program?->name ?? 'another program').'. You can apply to a different program only after it is completed, rejected, or cancelled.',
            ]);
        }

        $active = Application::query()
            ->where('applicant_id', $applicant->id)
            ->where('assistance_program_id', $program->id)
            ->whereNotIn('status', [
                ApplicationStatus::Rejected->value,
                ApplicationStatus::Cancelled->value,
                ApplicationStatus::Completed->value,
            ])->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'program' => 'You already have an active application for this program.',
            ]);
        }

        if (! $program->isCurrentlyOpen()) {
            throw ValidationException::withMessages([
                'program' => 'This program is not currently open for applications.',
            ]);
        }

        if (! $program->acceptsBeneficiary($applicant->beneficiaryType())) {
            throw ValidationException::withMessages([
                'program' => 'You are not in the beneficiary type covered by this program.',
            ]);
        }

        return DB::transaction(function () use ($applicant, $program) {
            $application = Application::query()->create([
                'application_no' => $this->generateNumber(gov('application_prefix', 'CAMS'), 'applications', 'application_no'),
                'applicant_id' => $applicant->id,
                'assistance_program_id' => $program->id,
                'status' => ApplicationStatus::Draft,
                'current_step' => 1,
            ]);

            $this->recordHistory($application, null, ApplicationStatus::Draft, $applicant->user, 'Application draft created.');
            $this->audit->log('created', 'Started application '.$application->application_no.' for '.$program->name, $application, $application, $applicant->user);

            return $application;
        });
    }

    public function confirmEligibility(Application $application, User $user): void
    {
        $application->update(['current_step' => max($application->current_step, 2)]);
        $this->audit->log('updated', 'Eligibility checklist confirmed for '.$application->application_no, $application, $application, $user);
    }

    public function saveAnswers(Application $application, array $answers, User $user): void
    {
        $fields = $application->program->formFields;

        DB::transaction(function () use ($application, $answers, $fields) {
            foreach ($fields as $field) {
                $value = $answers[$field->name] ?? null;
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                ApplicationAnswer::query()->updateOrCreate(
                    [
                        'application_id' => $application->id,
                        'field_name' => $field->name,
                    ],
                    [
                        'program_form_field_id' => $field->id,
                        'field_label' => $field->label,
                        'value' => $value,
                    ]
                );
            }

            $application->update(['current_step' => max($application->current_step, 3)]);
        });

        $this->audit->log('updated', 'Application form saved for '.$application->application_no, $application, $application, $user);
    }

    public function storeDocument(
        Application $application,
        int $requirementId,
        UploadedFile $file,
        User $user,
    ): DocumentSubmission {
        $requirement = $application->program->requirements->firstWhere('id', $requirementId);

        if (! $requirement) {
            throw ValidationException::withMessages([
                'document' => 'The selected requirement is not part of this program.',
            ]);
        }

        $existing = $application->documentForRequirement($requirementId);
        if ($existing) {
            DocumentFiles::delete($existing->file_path);
            $existing->verification()?->delete();
            $existing->delete();
        }

        $path = DocumentFiles::store($file, $application->id);

        $document = DocumentSubmission::query()->create([
            'application_id' => $application->id,
            'program_requirement_id' => $requirement->id,
            'requirement_name' => $requirement->name,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        DocumentVerification::query()->create([
            'document_submission_id' => $document->id,
            'status' => DocumentVerificationStatus::Pending,
        ]);

        $application->update(['current_step' => max($application->current_step, 4)]);
        $this->audit->log('updated', 'Uploaded requirement "'.$requirement->name.'" for '.$application->application_no, $application, $document, $user);
        $this->returnToVerificationAfterReplacement($application, $user);
        $this->ocr->process($document);

        return $document;
    }

    public function returnToVerificationAfterReplacement(Application $application, ?User $user = null, bool $onlyWhenAllReplaced = false): void
    {
        $application->unsetRelation('documents');
        $application->refresh();
        $application->load(['documents.verification', 'program.requirements', 'applicant.user']);

        if (! in_array($application->status, [
            ApplicationStatus::ForRevision,
            ApplicationStatus::Incomplete,
        ], true)) {
            return;
        }

        if ($onlyWhenAllReplaced && $application->documentsNeedingAction()->isNotEmpty()) {
            return;
        }

        $actor = $user ?? $application->applicant?->user;

        if (! $actor) {
            return;
        }

        $this->changeStatus(
            $application,
            ApplicationStatus::UnderVerification,
            $actor,
            'Replacement document uploaded. Application returned to verification.'
        );

        $this->notifications->notifyApplicant(
            $application,
            'Document replaced',
            'Your replacement for application '.$application->application_no.' was received and is now under verification.',
            'info'
        );
    }

    public function submit(Application $application, User $user): void
    {
        $this->assertRequiredDocuments($application);

        $from = $application->status;

        if (in_array($from, [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification], true)) {
            return;
        }

        $to = in_array($from, [ApplicationStatus::ForRevision, ApplicationStatus::Incomplete], true)
            ? ApplicationStatus::UnderVerification
            : ApplicationStatus::Submitted;

        $application->update([
            'status' => $to,
            'submitted_at' => $application->submitted_at ?? now(),
            'current_step' => 5,
        ]);

        $this->syncAssignedStaff($application);
        $verifier = $application->assignedStaff;

        $this->recordHistory($application, $from, $to, $user, 'Application submitted for processing.');
        $this->audit->log('updated', 'Submitted application '.$application->application_no, $application, $application, $user);

        $this->notifications->notifyApplicant(
            $application,
            'Application submitted',
            'Your application '.$application->application_no.' has been submitted and is awaiting document verification'
                .($verifier ? ' by '.$verifier->name : '')
                .'.',
            'success'
        );
    }

    public function assignToWorkflowStep(Application $application, WorkflowStep $step): void
    {
        $members = WorkflowStaff::staffMembersForStep($step);

        if ($members->isEmpty()) {
            return;
        }

        if ($application->assigned_staff_id && $members->contains(fn (User $user) => $user->id === $application->assigned_staff_id)) {
            $application->unsetRelation('assignedStaff');
            $application->load('assignedStaff');

            return;
        }

        $statuses = array_map(fn (ApplicationStatus $status) => $status->value, $step->queueStatuses());
        $staff = $members->sortBy(function (User $user) use ($statuses) {
            return Application::query()
                ->where('assigned_staff_id', $user->id)
                ->whereIn('status', $statuses)
                ->count();
        })->first();

        if ($staff && $application->assigned_staff_id !== $staff->id) {
            $application->update(['assigned_staff_id' => $staff->id]);
        }

        $application->unsetRelation('assignedStaff');
        $application->load('assignedStaff');
    }

    public function syncAssignedStaff(Application $application): void
    {
        if (! in_array($application->status, ApplicationStatus::processingQueue(), true)) {
            return;
        }

        $this->assignToWorkflowStep($application, $application->currentWorkflowStep());
    }

    public function syncOpenAssignments(): int
    {
        $updated = 0;

        Application::query()
            ->whereIn('status', array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::processingQueue()))
            ->each(function (Application $application) use (&$updated) {
                $before = $application->assigned_staff_id;
                $this->syncAssignedStaff($application);

                if ($application->assigned_staff_id !== $before) {
                    $updated++;
                }
            });

        return $updated;
    }

    public function changeStatus(
        Application $application,
        ApplicationStatus $to,
        User $user,
        ?string $remarks = null,
        string $auditAction = 'updated',
    ): void {
        $from = $application->status;
        $application->update([
            'status' => $to,
            'remarks' => $remarks ?? $application->remarks,
        ]);
        $this->syncAssignedStaff($application);

        $this->recordHistory($application, $from, $to, $user, $remarks);
        $this->audit->log(
            $auditAction,
            'Changed status of '.$application->application_no.' from '.$from->label().' to '.$to->label(),
            $application,
            $application,
            $user
        );
    }

    public function cancel(Application $application, User $user, string $remarks): void
    {
        $this->changeStatus($application, ApplicationStatus::Cancelled, $user, $remarks);
        $this->notifications->notifyApplicant(
            $application,
            'Application cancelled',
            'Application '.$application->application_no.' has been cancelled. '.$remarks,
            'warning'
        );
    }

    public function recordHistory(
        Application $application,
        ApplicationStatus|string|null $from,
        ApplicationStatus $to,
        ?User $user,
        ?string $remarks = null,
    ): void {
        $fromValue = $from instanceof ApplicationStatus ? $from->value : $from;

        $application->statusHistory()->create([
            'from_status' => $fromValue,
            'to_status' => $to->value,
            'user_id' => $user?->id,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }

    private function assertRequiredDocuments(Application $application): void
    {
        $application->loadMissing(['documents', 'program.requirements']);

        $missing = [];
        foreach ($application->program->requirements as $requirement) {
            if ($requirement->is_required && ! $application->documentForRequirement($requirement->id)) {
                $missing[] = $requirement->name;
            }
        }

        if ($missing) {
            throw ValidationException::withMessages([
                'documents' => 'Please upload the required documents: '.implode(', ', $missing).'.',
            ]);
        }

        $application->loadMissing('documents.verification');
        $needsRevision = $application->documentsNeedingAction()
            ->map(fn (DocumentSubmission $document) => $document->requirement_name)
            ->all();

        if ($needsRevision) {
            throw ValidationException::withMessages([
                'documents' => 'Please replace the documents requested for revision: '.implode(', ', $needsRevision).'.',
            ]);
        }
    }
}
