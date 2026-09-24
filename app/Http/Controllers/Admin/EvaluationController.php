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

class EvaluationController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        abort_unless(
            $user->isAdmin() || WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Evaluation),
            403,
            'You are not assigned to this workflow step.',
        );

        $applications = Application::query()
            ->with(['applicant', 'program.category', 'latestEvaluation'])
            ->where('status', ApplicationStatus::UnderEvaluation->value)
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('Admin/Queue', [
            'title' => 'Applications for Evaluation',
            'kicker' => 'Documents verified — ready for evaluation',
            'actionLabel' => 'Evaluate',
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
        ]);
    }
}
