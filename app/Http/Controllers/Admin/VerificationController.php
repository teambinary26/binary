<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\WorkflowStep;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\WorkflowStaff;
use App\Support\CamData;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        abort_unless(
            $user->isAdmin() || WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Verification),
            403,
            'You are not assigned to this workflow step.',
        );

        $applications = Application::query()
            ->with(['applicant', 'program.category'])
            ->whereIn('status', [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::UnderVerification->value,
                ApplicationStatus::Incomplete->value,
                ApplicationStatus::ForRevision->value,
            ])
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('Admin/Queue', [
            'title' => 'Pending Verification',
            'kicker' => 'Submitted and unverified applications',
            'actionLabel' => 'Verify',
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
        ]);
    }
}
