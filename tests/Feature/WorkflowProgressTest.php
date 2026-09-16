<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\WorkflowStep;
use App\Mail\DocumentRevisionMail;
use App\Models\Application;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkflowStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('admin can assign multiple staff members to one workflow step', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.settings.edit'))
        ->put(route('admin.settings.workflow'), [
            'assignments' => [
                [
                    'workflow_step' => WorkflowStep::Verification->value,
                    'user_ids' => [$staff->id, $admin->id],
                ],
                [
                    'workflow_step' => WorkflowStep::Evaluation->value,
                    'user_ids' => [$staff->id],
                ],
                [
                    'workflow_step' => WorkflowStep::Approval->value,
                    'user_ids' => [$admin->id],
                ],
            ],
        ])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('success');

    expect(WorkflowStaff::isAssignedToStep($staff->id, WorkflowStep::Verification))->toBeTrue()
        ->and(WorkflowStaff::isAssignedToStep($admin->id, WorkflowStep::Verification))->toBeTrue()
        ->and(WorkflowStaff::namesForStep(WorkflowStep::Verification))->toContain($staff->name)
        ->and(WorkflowStaff::namesForStep(WorkflowStep::Verification))->toContain($admin->name);

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workflowSteps.0.key', WorkflowStep::Verification->value)
            ->where('workflowSteps.0.assigned_user_ids', function ($ids) use ($staff, $admin) {
                return collect($ids)->contains($staff->id) && collect($ids)->contains($admin->id);
            })
        );

    $application = Application::query()
        ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification])
        ->has('documents')
        ->with('documents')
        ->firstOrFail();
    $document = $application->documents->first();

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.documents.verify', [$application, $document]), [
            'action' => 'verify',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('submitting a completed application assigns verification staff', function () {
    $verifier = WorkflowStaff::staffForStep(WorkflowStep::Verification);
    expect($verifier)->not->toBeNull();

    $source = Application::query()
        ->has('documents')
        ->has('applicant.user')
        ->with(['documents', 'applicant.user', 'program.requirements'])
        ->get()
        ->first(fn (Application $application) => $application->missingRequiredRequirements()->isEmpty());

    expect($source)->not->toBeNull();

    $application = $source->replicate(['application_no', 'status', 'assigned_staff_id', 'submitted_at']);
    $application->application_no = 'CAMS-2026-TEST01';
    $application->status = ApplicationStatus::Accepted;
    $application->assigned_staff_id = null;
    $application->submitted_at = null;
    $application->save();

    foreach ($source->documents as $document) {
        $copy = $document->replicate();
        $copy->application_id = $application->id;
        $copy->save();
    }

    $source->loadMissing('answers');
    foreach ($source->answers as $answer) {
        $copy = $answer->replicate();
        $copy->application_id = $application->id;
        $copy->save();
    }

    $this->actingAs($source->applicant->user)
        ->post(route('applicant.apply.submit', $application))
        ->assertRedirect(route('applicant.applications.show', $application));

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->assigned_staff_id)->toBe($verifier->id);
});

test('verifying all documents does not skip evaluation until marked as done', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->where('status', ApplicationStatus::Submitted)
        ->has('documents')
        ->with(['documents', 'program.requirements'])
        ->firstOrFail();

    foreach ($application->documents as $document) {
        $this->actingAs($admin)
            ->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.documents.verify', [$application, $document]), [
                'action' => 'verify',
            ])
            ->assertRedirect();
    }

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::UnderVerification)
        ->and($application->canCompleteVerification())->toBeTrue();

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.complete-verification', $application))
        ->assertRedirect()
        ->assertSessionHas('success');

    $application->refresh();
    $evaluator = WorkflowStaff::staffForStep(WorkflowStep::Evaluation);

    expect($application->status)->toBe(ApplicationStatus::UnderEvaluation)
        ->and($application->assigned_staff_id)->toBe($evaluator?->id);
});

test('recording an evaluation sends the application to approval', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->where('status', ApplicationStatus::UnderEvaluation)
        ->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.evaluate', $application), [
            'eligibility_passed' => true,
            'documents_complete' => true,
            'assessment' => 'Eligible and complete.',
            'recommendation' => 'approval',
            'recommended_amount' => 2000,
            'remarks' => 'Recommended for approval.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $application->refresh();
    $approver = WorkflowStaff::staffForStep(WorkflowStep::Approval);

    expect($application->status)->toBe(ApplicationStatus::ForApproval)
        ->and($application->assigned_staff_id)->toBe($approver?->id)
        ->and($application->latestEvaluation?->recommendation)->toBe('approval');
});

test('staff assigned to verification can verify a document', function () {
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification])
        ->has('documents')
        ->with('documents')
        ->firstOrFail();
    $document = $application->documents->first();

    $this->actingAs($staff)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.documents.verify', [$application, $document]), [
            'action' => 'verify',
        ])
        ->assertRedirect(route('admin.applications.show', $application))
        ->assertSessionHas('success');

    expect($document->fresh()->verification?->status)->toBe(DocumentVerificationStatus::Verified);
});

test('application show offers the next application in the staff member assigned step', function () {
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $queue = Application::query()
        ->where('assigned_staff_id', $staff->id)
        ->where('status', ApplicationStatus::UnderEvaluation)
        ->orderBy('application_no')
        ->orderBy('id')
        ->get();

    expect($queue->count())->toBeGreaterThan(1);

    $current = $queue->first();
    $next = $queue->get(1);

    $this->actingAs($staff)
        ->get(route('admin.applications.show', $current))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Applications/Show')
            ->where('assignedQueue.step', WorkflowStep::Evaluation->value)
            ->where('assignedQueue.next.id', $next->id)
            ->where('assignedQueue.next.application_no', $next->application_no)
            ->where('assignedQueue.total', $queue->count())
            ->where('assignedQueue.position', 1)
        );
});

test('next application stays in the staff member assigned workflow step', function () {
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    WorkflowStaff::query()->where('workflow_step', WorkflowStep::Evaluation->value)->delete();
    WorkflowStaff::query()->updateOrCreate(
        ['workflow_step' => WorkflowStep::Evaluation->value],
        ['user_id' => $admin->id],
    );

    app(\App\Services\ApplicationService::class)->syncOpenAssignments();

    $verificationQueue = Application::query()
        ->where('assigned_staff_id', $staff->id)
        ->whereIn('status', [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
            ApplicationStatus::Incomplete,
        ])
        ->orderBy('application_no')
        ->orderBy('id')
        ->get();

    $evaluation = Application::query()
        ->where('status', ApplicationStatus::UnderEvaluation)
        ->firstOrFail();

    expect($verificationQueue->count())->toBeGreaterThan(1);

    $this->actingAs($staff)
        ->get(route('admin.applications.show', $evaluation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignedQueue.step', WorkflowStep::Verification->value)
            ->where('assignedQueue.next.id', $verificationQueue->first()->id)
            ->where('assignedQueue.next.application_no', $verificationQueue->first()->application_no)
        );

    $current = $verificationQueue->first();
    $next = $verificationQueue->get(1);

    $this->actingAs($staff)
        ->get(route('admin.applications.show', ['application' => $current, 'step' => WorkflowStep::Verification->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignedQueue.step', WorkflowStep::Verification->value)
            ->where('assignedQueue.next.id', $next->id)
            ->where('assignedQueue.position', 1)
        );
});

test('requesting document revision notifies the applicant and lets them replace the file', function () {
    Mail::fake();
    Storage::fake('public');
    Http::fake([
        'https://api.ocr.space/*' => Http::response([
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [['ParsedText' => 'Replacement document']],
        ], 200),
    ]);

    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification])
        ->has('documents')
        ->has('applicant.user')
        ->with(['documents', 'applicant.user'])
        ->firstOrFail();
    $document = $application->documents->firstWhere('program_requirement_id')
        ?? $application->documents->firstOrFail();
    $applicantUser = $application->applicant->user;
    $applicantUser->update(['is_active' => true]);

    $this->actingAs($staff)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.documents.verify', [$application, $document]), [
            'action' => 'revision',
            'remarks' => 'The ID photo is blurry. Please upload a clearer copy.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::ForRevision)
        ->and($document->fresh()->verification?->status)->toBe(DocumentVerificationStatus::RevisionRequested);

    $this->actingAs($staff)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.documents.verify', [$application, $document]), [
            'action' => 'verify',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('document');

    $this->actingAs($staff)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('application.status', ApplicationStatus::ForRevision->value)
            ->where('application.is_in_verification', true)
        );

    expect(SystemNotification::query()
        ->where('user_id', $applicantUser->id)
        ->where('application_id', $application->id)
        ->where('title', 'Document revision required')
        ->exists())->toBeTrue();

    Mail::assertSent(DocumentRevisionMail::class, function (DocumentRevisionMail $mail) use ($application, $applicantUser) {
        $email = $application->applicant->email ?: $applicantUser->email;

        return $mail->hasTo($email) && $mail->application->is($application);
    });

    $this->actingAs($applicantUser)
        ->get(route('applicant.apply.documents', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Apply/Documents')
            ->where('application.needs_document_action', true)
            ->has('application.revision_documents', 1)
        );

    $this->actingAs($applicantUser)
        ->from(route('applicant.apply.review', $application))
        ->post(route('applicant.apply.submit', $application))
        ->assertRedirect(route('applicant.apply.review', $application))
        ->assertSessionHasErrors('documents');

    $this->actingAs($applicantUser)
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $document->program_requirement_id,
            'file' => UploadedFile::fake()->image('clearer-id.jpg'),
        ])
        ->assertRedirect(route('applicant.applications.show', $application))
        ->assertSessionHas('success');

    $application->refresh()->load(['documents.verification', 'statusHistory']);
    $replacement = $application->documentForRequirement($document->program_requirement_id);

    expect($replacement)->not->toBeNull()
        ->and($replacement->verification?->status)->toBe(DocumentVerificationStatus::Pending)
        ->and($application->status)->toBe(ApplicationStatus::UnderVerification)
        ->and($application->statusHistory->contains(
            fn ($history) => $history->to_status === ApplicationStatus::UnderVerification
        ))->toBeTrue();

    expect(SystemNotification::query()
        ->where('user_id', $applicantUser->id)
        ->where('application_id', $application->id)
        ->where('title', 'Document replaced')
        ->exists())->toBeTrue();

    $this->actingAs($staff)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.documents.verify', [$application, $replacement]), [
            'action' => 'verify',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($replacement->fresh()->verification?->status)->toBe(DocumentVerificationStatus::Verified)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::UnderVerification);
});

test('replacing one revision document returns the application to under verification', function () {
    Mail::fake();
    Storage::fake('public');
    Http::fake([
        'https://api.ocr.space/*' => Http::response([
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [['ParsedText' => 'Replacement document']],
        ], 200),
    ]);

    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification])
        ->has('documents', '>=', 2)
        ->has('applicant.user')
        ->with(['documents', 'applicant.user'])
        ->firstOrFail();
    $documents = $application->documents
        ->filter(fn ($document) => $document->program_requirement_id)
        ->take(2)
        ->values();
    $applicantUser = $application->applicant->user;
    $applicantUser->update(['is_active' => true]);

    expect($documents)->toHaveCount(2);

    foreach ($documents as $document) {
        $this->actingAs($staff)
            ->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.documents.verify', [$application, $document]), [
                'action' => 'revision',
                'remarks' => 'Please upload a clearer copy of '.$document->requirement_name.'.',
            ])
            ->assertRedirect();
    }

    expect($application->fresh()->status)->toBe(ApplicationStatus::ForRevision);

    $this->actingAs($applicantUser)
        ->from(route('applicant.apply.documents', $application))
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $documents[0]->program_requirement_id,
            'file' => UploadedFile::fake()->image('first-replacement.jpg'),
        ])
        ->assertRedirect(route('applicant.apply.documents', $application));

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderVerification)
        ->and($application->fresh()->needsDocumentAction())->toBeTrue();

    $this->actingAs($applicantUser)
        ->from(route('applicant.apply.documents', $application))
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $documents[1]->program_requirement_id,
            'file' => UploadedFile::fake()->image('second-replacement.jpg'),
        ])
        ->assertRedirect(route('applicant.applications.show', $application));

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderVerification)
        ->and($application->fresh()->needsDocumentAction())->toBeFalse();
});

test('opening a for-revision application with replacements already uploaded returns it to verification', function () {
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification])
        ->has('documents.verification')
        ->firstOrFail();

    $application->update(['status' => ApplicationStatus::ForRevision]);
    $application->documents()->with('verification')->get()->each(function ($document) {
        $document->verification?->update(['status' => DocumentVerificationStatus::Pending]);
    });

    $this->actingAs($staff)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('application.status', ApplicationStatus::UnderVerification->value)
        );

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderVerification);
});
