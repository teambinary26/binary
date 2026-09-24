<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\WorkflowStep;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\DocumentSubmission;
use App\Models\User;
use App\Models\WorkflowStaff;
use App\Services\ApplicationService;
use App\Services\ApprovalService;
use App\Services\DocumentOcrService;
use App\Services\AuditService;
use App\Services\EvaluationService;
use App\Services\VerificationService;
use App\Support\CamData;
use App\Support\DocumentFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request, ApplicationService $applicationService): Response
    {
        $applicationService->syncOpenAssignments();

        $applications = Application::query()
            ->with(['applicant.primaryAddress', 'program.category', 'assignedStaff'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('program'), fn ($q) => $q->where('assistance_program_id', $request->integer('program')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = '%'.$request->string('q').'%';
                $q->where(function ($query) use ($search) {
                    $query->where('application_no', 'like', $search)
                        ->orWhereHas('applicant', fn ($a) => $a->where('full_name', 'like', $search));
                });
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $rawCounts = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $countFor = fn (array $values) => collect($values)->sum(fn ($v) => (int) ($rawCounts[$v] ?? 0));

        $counts = [
            'total' => array_sum($rawCounts),
            'draft' => $countFor([ApplicationStatus::Draft->value]),
            'in_progress' => $countFor([
                ApplicationStatus::Accepted->value,
                ApplicationStatus::Submitted->value,
                ApplicationStatus::UnderVerification->value,
                ApplicationStatus::UnderEvaluation->value,
                ApplicationStatus::ForApproval->value,
                ApplicationStatus::Incomplete->value,
                ApplicationStatus::ForRevision->value,
            ]),
            'approved' => $countFor([
                ApplicationStatus::Approved->value,
                ApplicationStatus::ScheduledForRelease->value,
                ApplicationStatus::Released->value,
                ApplicationStatus::Completed->value,
            ]),
            'rejected' => $countFor([
                ApplicationStatus::Rejected->value,
                ApplicationStatus::Cancelled->value,
            ]),
        ];

        return Inertia::render('Admin/Applications/Index', [
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
            'programs' => AssistanceProgram::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => CamData::statuses(),
            'filters' => $request->only(['status', 'program', 'q']),
            'counts' => $counts,
            'canDelete' => (bool) $request->user()?->isAdmin(),
        ]);
    }

    public function destroy(Request $request, Application $application, AuditService $audit): RedirectResponse
    {
        $this->authorize('delete', $application);

        $applicationNo = $application->application_no;

        DB::transaction(function () use ($application) {
            $this->deleteApplicationRecord($application);
        });

        $audit->log('deleted', 'Deleted application '.$applicationNo, null, null, $request->user());

        return redirect()->route('admin.applications.index')
            ->with('success', 'Application '.$applicationNo.' has been deleted.');
    }

    public function bulkDestroy(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:applications,id'],
        ]);

        $applications = Application::query()
            ->whereIn('id', $data['ids'])
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            $this->authorize('delete', $application);
        }

        $numbers = $applications->pluck('application_no')->all();

        DB::transaction(function () use ($applications) {
            foreach ($applications as $application) {
                $this->deleteApplicationRecord($application);
            }
        });

        $count = count($numbers);
        $audit->log(
            'deleted',
            $count === 1
                ? 'Deleted application '.$numbers[0]
                : 'Deleted '.$count.' applications: '.implode(', ', $numbers),
            null,
            null,
            $request->user(),
        );

        return redirect()->route('admin.applications.index')
            ->with('success', $count === 1
                ? 'Application '.$numbers[0].' has been deleted.'
                : $count.' applications have been deleted.');
    }

    public function show(Application $application, ApplicationService $applicationService): Response
    {
        $applicationService->returnToVerificationAfterReplacement($application, auth()->user(), onlyWhenAllReplaced: true);
        $applicationService->syncAssignedStaff($application);
        $application->refresh();

        $application->load('documents');
        foreach ($application->documents as $document) {
            app(DocumentOcrService::class)->ensureScanned($document);
        }
        $application->unsetRelation('documents');

        $application->load([
            'applicant.profile', 'applicant.primaryAddress', 'applicant.user',
            'program.category', 'program.requirements', 'program.eligibilityRules', 'program.formFields',
            'answers', 'documents.verification.verifier', 'documents.ocrResult.fields', 'statusHistory.user',
            'evaluations.evaluator', 'approvals.officer', 'assignedStaff',
            'releaseSchedules.scheduler', 'releases.officer', 'latestEvaluation.evaluator',
            'latestApproval.officer', 'latestSchedule', 'latestRelease',
        ]);

        $globalAssignments = WorkflowStaff::allAssignments();

        $workflowSteps = collect(WorkflowStep::ordered())->map(function (WorkflowStep $step) use ($application, $globalAssignments) {
            $assigned = $globalAssignments->where('workflow_step', $step);
            $currentStep = $application->currentWorkflowStep();

            $state = 'upcoming';
            if ($step->order() < $currentStep->order()) {
                $state = 'done';
            } elseif ($step === $currentStep) {
                $state = 'current';
            }

            return [
                'key' => $step->value,
                'label' => $step->label(),
                'description' => $step->description(),
                'order' => $step->order(),
                'state' => $state,
                'assigned_user_name' => $assigned->map(fn ($row) => $row->user?->name)->filter()->implode(', ') ?: null,
            ];
        });

        return Inertia::render('Admin/Applications/Show', [
            'application' => CamData::applicationDetail($application),
            'staff' => User::query()
                ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'applicant'))
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role?->name,
                ])
                ->values(),
            'workflowSteps' => $workflowSteps,
            'assignedQueue' => $application->assignedQueueNavigation(
                request()->user(),
                request()->enum('step', WorkflowStep::class),
            ),
            'abilities' => [
                'verify' => request()->user()->can('verify', $application),
                'evaluate' => request()->user()->can('evaluate', $application),
                'approve' => request()->user()->can('approve', $application),
            ],
        ]);
    }

    public function assign(Request $request, Application $application): RedirectResponse
    {
        $data = $request->validate(['assigned_staff_id' => ['nullable', 'exists:users,id']]);
        $application->update($data);

        return back()->with('success', 'Assigned staff updated.');
    }

    public function download(Application $application, DocumentSubmission $document): StreamedResponse
    {
        abort_unless($document->application_id === $application->id, 404);

        return DocumentFiles::download($document->file_path, $document->original_name);
    }

    public function preview(Application $application, DocumentSubmission $document): StreamedResponse
    {
        abort_unless($document->application_id === $application->id, 404);

        return DocumentFiles::inline($document->file_path, $document->original_name);
    }

    public function verifyDocument(Request $request, Application $application, DocumentSubmission $document, VerificationService $verification): RedirectResponse
    {
        abort_unless($document->application_id === $application->id, 404);
        $this->authorize('verify', $application);

        $action = $request->validate([
            'action' => ['required', 'in:verify,reject,revision'],
            'remarks' => ['nullable', 'string'],
        ]);

        $verification->markUnderVerification($application, $request->user());

        match ($action['action']) {
            'verify' => $verification->verifyDocument($document, $request->user(), $action['remarks'] ?? null),
            'reject' => $verification->rejectDocument($document, $request->user(), $action['remarks'] ?? ''),
            'revision' => $verification->requestRevision($document, $request->user(), $action['remarks'] ?? ''),
        };

        $message = match ($action['action']) {
            'revision' => 'Revision requested. The applicant was notified to replace this document.',
            'reject' => 'Document rejected. The applicant was notified.',
            default => 'Document verification updated.',
        };

        return back()->with('success', $message);
    }

    public function processOcr(Request $request, Application $application, DocumentSubmission $document, DocumentOcrService $ocr): RedirectResponse
    {
        abort_unless($document->application_id === $application->id, 404);
        $this->authorize('verify', $application);

        $result = $ocr->process($document, notifyOnMismatch: false);

        $message = $result->status === 'failed'
            ? ($result->error_message ?: 'OCR could not read this file.')
            : ($result->summary ?: 'OCR finished. Staff must still verify the document.');

        return back()->with($result->status === 'failed' ? 'warning' : 'success', $message);
    }

    public function updateOcrFields(Request $request, Application $application, DocumentSubmission $document, DocumentOcrService $ocr): RedirectResponse
    {
        abort_unless($document->application_id === $application->id, 404);
        $this->authorize('verify', $application);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer'],
            'fields.*.corrected_value' => ['nullable', 'string'],
        ]);

        $result = $document->ocrResult;

        if (! $result) {
            return back()->with('warning', 'Run OCR first before editing extracted fields.');
        }

        $ocr->updateFields($result, $data['fields']);

        return back()->with('success', 'OCR fields updated. Matching was recalculated. This does not verify the document.');
    }

    public function scanEligibility(Application $application, DocumentOcrService $ocr): RedirectResponse
    {
        $this->authorize('evaluate', $application);

        $application->load('documents.ocrResult');
        $scanned = 0;

        foreach ($application->documents as $document) {
            if ($document->ocrResult?->status === 'processed' && filled($document->ocrResult->raw_text)) {
                continue;
            }

            $ocr->process($document, notifyOnMismatch: false);
            $scanned++;
        }

        return back()->with('success', $scanned > 0
            ? 'Documents were scanned. Review the eligibility result before saving the evaluation.'
            : 'OCR text was already available. Review the eligibility result before saving.');
    }

    public function evaluate(Request $request, Application $application, EvaluationService $evaluation): RedirectResponse
    {
        $this->authorize('evaluate', $application);

        $data = $request->validate([
            'eligibility_passed' => ['nullable', 'boolean'],
            'eligibility_checks' => ['nullable', 'array'],
            'eligibility_checks.*.id' => ['required', 'integer'],
            'eligibility_checks.*.label' => ['nullable', 'string'],
            'eligibility_checks.*.check_mode' => ['nullable', 'in:ocr,manual'],
            'eligibility_checks.*.status' => ['required', 'in:passed,failed,review'],
            'documents_complete' => ['nullable', 'boolean'],
            'assessment' => ['nullable', 'string'],
            'recommendation' => ['required', 'in:approval,rejection,revision'],
            'recommended_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['required', 'string'],
        ]);

        $evaluation->evaluate($application, $request->user(), $data);

        if ($data['recommendation'] === 'revision') {
            return back()->with('success', 'Evaluation recorded. The application was returned for revision.');
        }

        return redirect()
            ->route('admin.applications.show', [
                'application' => $application,
                'step' => WorkflowStep::Evaluation->value,
            ])
            ->with('success', 'Evaluation recorded. The application was sent for approval.');
    }

    public function completeVerification(Request $request, Application $application, VerificationService $verification): RedirectResponse
    {
        $this->authorize('verify', $application);
        $verification->completeVerification($application, $request->user());

        return redirect()
            ->route('admin.applications.show', [
                'application' => $application,
                'step' => WorkflowStep::Verification->value,
            ])
            ->with('success', 'Verification marked as done. The application was sent for evaluation.');
    }

    public function completeEvaluation(Request $request, Application $application, EvaluationService $evaluation): RedirectResponse
    {
        $this->authorize('evaluate', $application);
        $evaluation->forwardToApproval($application, $request->user());

        return redirect()
            ->route('admin.applications.show', [
                'application' => $application,
                'step' => WorkflowStep::Evaluation->value,
            ])
            ->with('success', 'Evaluation completed. The application was sent for approval.');
    }

    public function decide(Request $request, Application $application, ApprovalService $approval): RedirectResponse
    {
        $this->authorize('approve', $application);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,revision'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $approval->decide($application, $request->user(), $data);

        return back()->with('success', 'Approval action recorded.');
    }

    public function approveApplicant(Request $request, Application $application, ApprovalService $approval): RedirectResponse
    {
        $this->authorize('approve', $application);

        $data = $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        $approval->approveApplicant($application, $request->user(), $data['remarks'] ?? null);

        return back()->with('success', 'Application accepted.');
    }

    public function rejectApplicant(Request $request, Application $application, ApprovalService $approval): RedirectResponse
    {
        $this->authorize('approve', $application);

        $data = $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $approval->rejectApplicant($application, $request->user(), $data['remarks']);

        return back()->with('success', 'Application rejected. The applicant has been notified.');
    }

    private function deleteApplicationRecord(Application $application): void
    {
        $application->loadMissing('documents');

        foreach ($application->documents as $document) {
            DocumentFiles::delete($document->file_path);
            $document->verification()?->delete();
            $document->delete();
        }

        $application->answers()->delete();
        $application->statusHistory()->delete();
        $application->evaluations()->delete();
        $application->approvals()->delete();
        $application->releaseSchedules()->delete();
        $application->releases()->delete();
        $application->delete();
    }
}
