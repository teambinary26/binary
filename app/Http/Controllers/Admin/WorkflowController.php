<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\WorkflowStep;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\WorkflowStaff;
use App\Support\CamData;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        $stages = [
            [
                'key' => 'verification',
                'order' => 1,
                'title' => 'Pending Verification',
                'description' => 'Verify submitted documents and requirements.',
                'tone' => 'info',
                'route' => 'admin.verification.index',
                'action_label' => 'Open verification queue',
                'can_open' => $user->isAdmin() || WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Verification),
                'assigned_staff' => WorkflowStaff::namesForStep(WorkflowStep::Verification),
                'query' => fn (Builder $q) => $q->whereIn('status', [
                    ApplicationStatus::Submitted->value,
                    ApplicationStatus::UnderVerification->value,
                    ApplicationStatus::Incomplete->value,
                    ApplicationStatus::ForRevision->value,
                ]),
            ],
            [
                'key' => 'evaluation',
                'order' => 2,
                'title' => 'For Evaluation',
                'description' => 'Evaluate application eligibility and recommend action.',
                'tone' => 'warning',
                'route' => 'admin.evaluation.index',
                'action_label' => 'Open evaluation queue',
                'can_open' => $user->isAdmin() || WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Evaluation),
                'assigned_staff' => WorkflowStaff::namesForStep(WorkflowStep::Evaluation),
                'query' => fn (Builder $q) => $q->where('status', ApplicationStatus::UnderEvaluation->value),
            ],
            [
                'key' => 'approval',
                'order' => 3,
                'title' => 'Approved / For Approval',
                'description' => 'Final approval or rejection of the application.',
                'tone' => 'success',
                'route' => 'admin.approvals.index',
                'action_label' => 'Open approval queue',
                'can_open' => $user->isAdmin() || WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Approval),
                'assigned_staff' => WorkflowStaff::namesForStep(WorkflowStep::Approval),
                'query' => fn (Builder $q) => $q->whereIn('status', [
                    ApplicationStatus::ForApproval->value,
                    ApplicationStatus::Approved->value,
                ]),
            ],
            [
                'key' => 'rejected',
                'order' => null,
                'title' => 'Rejected',
                'description' => 'Applications that were not approved.',
                'tone' => 'danger',
                'route' => 'admin.applications.index',
                'route_params' => ['status' => 'rejected'],
                'action_label' => 'View rejected',
                'can_open' => true,
                'assigned_staff' => null,
                'query' => fn (Builder $q) => $q->where('status', ApplicationStatus::Rejected->value),
            ],
            [
                'key' => 'for_revision',
                'order' => null,
                'title' => 'For Revision',
                'description' => 'Applications returned to the applicant for correction.',
                'tone' => 'warning',
                'route' => 'admin.applications.index',
                'route_params' => ['status' => 'for_revision'],
                'action_label' => 'View for revision',
                'can_open' => true,
                'assigned_staff' => null,
                'query' => fn (Builder $q) => $q->where('status', ApplicationStatus::ForRevision->value),
            ],
        ];

        $cards = collect($stages)->map(function (array $stage) {
            $base = Application::query()->with(['applicant', 'program.category']);
            ($stage['query'])($base);

            $count = (clone $base)->count();
            $applications = $stage['can_open']
                ? (clone $base)->latest('submitted_at')->limit(5)->get()->map(fn ($a) => CamData::applicationRow($a))->values()
                : collect();

            return [
                'key' => $stage['key'],
                'order' => $stage['order'],
                'title' => $stage['title'],
                'description' => $stage['description'],
                'tone' => $stage['tone'],
                'route' => $stage['route'],
                'route_params' => $stage['route_params'] ?? [],
                'action_label' => $stage['action_label'],
                'can_open' => $stage['can_open'],
                'assigned_staff' => $stage['assigned_staff'],
                'count' => $count,
                'applications' => $applications,
            ];
        })->values();

        return Inertia::render('Admin/Workflow', [
            'cards' => $cards,
            'canManageSettings' => $user->hasPermission('settings.manage'),
        ]);
    }
}
