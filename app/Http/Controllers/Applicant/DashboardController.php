<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Support\CamData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $applicant = $request->user()->applicant()->with(['profile', 'primaryAddress'])->first();
        $applications = $applicant->applications()
            ->with(['program.category', 'program.requirements', 'documents.verification'])
            ->latest()
            ->get();

        $counts = [
            'total' => $applications->count(),
            'pending' => $applications->whereIn('status', [
                ApplicationStatus::Accepted,
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderVerification,
                ApplicationStatus::UnderEvaluation,
                ApplicationStatus::ForApproval,
                ApplicationStatus::Incomplete,
                ApplicationStatus::ForRevision,
            ])->count(),
            'approved' => $applications->whereIn('status', [
                ApplicationStatus::Approved,
                ApplicationStatus::ScheduledForRelease,
            ])->count(),
            'completed' => $applications->whereIn('status', [
                ApplicationStatus::Released,
                ApplicationStatus::Completed,
            ])->count(),
            'rejected' => $applications->where('status', ApplicationStatus::Rejected)->count(),
        ];

        $current = $applications->first(fn ($application) => $application->canBeEditedByApplicant());
        $missing = $current ? $current->missingRequiredRequirements() : collect();
        $revisions = $current ? $current->documentsNeedingAction() : collect();

        return Inertia::render('Applicant/Dashboard', [
            'applicant' => CamData::applicant($applicant),
            'applications' => $applications->map(fn ($a) => CamData::applicationRow($a))->values(),
            'counts' => $counts,
            'requirementTask' => $current ? [
                'id' => $current->id,
                'application_no' => $current->application_no,
                'program_name' => $current->program?->name,
                'continue_path' => $current->continuePath(),
                'missing' => $missing->map(fn ($requirement) => [
                    'id' => $requirement->id,
                    'name' => $requirement->name,
                    'description' => $requirement->description,
                ])->values(),
                'revisions' => $revisions->map(fn ($document) => [
                    'id' => $document->id,
                    'name' => $document->requirement_name,
                    'description' => $document->verification?->remarks,
                    'status_label' => $document->verification?->status->label(),
                ])->values(),
                'ready_to_submit' => $missing->isEmpty() && $revisions->isEmpty(),
                'needs_revision' => $revisions->isNotEmpty(),
            ] : null,
        ]);
    }
}
