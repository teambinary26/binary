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

class ApprovalController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        abort_unless(
            $user->isAdmin() || WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Approval),
            403,
            'You are not assigned to this workflow step.',
        );

        $applications = Application::query()
            ->with(['applicant', 'program.category', 'latestEvaluation'])
            ->where(function ($q) {
                $q->where('status', ApplicationStatus::ForApproval->value)
                    ->orWhere('status', ApplicationStatus::Approved->value);
            })
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('Admin/Queue', [
            'title' => 'Approval Queue',
            'kicker' => 'Evaluated applications ready for a final decision',
            'actionLabel' => 'Review',
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
        ]);
    }
}
