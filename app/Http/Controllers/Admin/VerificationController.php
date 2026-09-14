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

        $applications = Application::query()
            ->with(['applicant', 'program.category'])
            ->whereIn('status', [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::UnderVerification->value,
                ApplicationStatus::Incomplete->value,
                ApplicationStatus::ForRevision->value,
            ])
            ->when(! $user->isAdmin(), function ($q) use ($user) {
                if (! WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Verification)) {
                    $q->whereRaw('0 = 1');
                }
            })
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
