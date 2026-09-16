<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceRelease;
use App\Support\CamData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $stats = [
            'applicants' => Applicant::query()->count(),
            'applications' => Application::query()->count(),
            'pending' => Application::query()->whereIn('status', [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::UnderVerification->value,
                ApplicationStatus::UnderEvaluation->value,
                ApplicationStatus::ForApproval->value,
                ApplicationStatus::Incomplete->value,
                ApplicationStatus::ForRevision->value,
            ])->count(),
            'approved' => Application::query()->whereIn('status', [
                ApplicationStatus::Approved->value,
                ApplicationStatus::ScheduledForRelease->value,
            ])->count(),
            'released' => Application::query()->whereIn('status', [
                ApplicationStatus::Released->value,
                ApplicationStatus::Completed->value,
            ])->count(),
            'amount' => peso(AssistanceRelease::query()->sum('amount'), false),
        ];

        $recent = Application::query()
            ->with(['applicant', 'program.category', 'assignedStaff'])
            ->latest('submitted_at')
            ->paginate(10);

        $byMonth = Application::query()
            ->get(['submitted_at', 'created_at'])
            ->groupBy(function (Application $row) {
                $date = $row->submitted_at ?? $row->created_at;

                return $date?->format('Y-m') ?? 'n/a';
            })
            ->map->count()
            ->sortKeys();

        $byProgram = Application::query()
            ->join('assistance_programs', 'assistance_programs.id', '=', 'applications.assistance_program_id')
            ->selectRaw('assistance_programs.name as name, COUNT(*) as total')
            ->groupBy('assistance_programs.id', 'assistance_programs.name')
            ->pluck('total', 'name');

        $byType = Applicant::query()
            ->join('applicant_profiles', 'applicant_profiles.applicant_id', '=', 'applicants.id')
            ->selectRaw('applicant_profiles.beneficiary_type as type, COUNT(*) as total')
            ->groupBy('applicant_profiles.beneficiary_type')
            ->pluck('total', 'type');

        $approvedRejected = [
            'Approved' => Application::query()->whereIn('status', [
                ApplicationStatus::Approved->value,
                ApplicationStatus::ScheduledForRelease->value,
                ApplicationStatus::Released->value,
                ApplicationStatus::Completed->value,
            ])->count(),
            'Rejected' => Application::query()->where('status', ApplicationStatus::Rejected->value)->count(),
        ];

        $amountByProgram = AssistanceRelease::query()
            ->join('applications', 'applications.id', '=', 'assistance_releases.application_id')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'applications.assistance_program_id')
            ->selectRaw('assistance_programs.name as name, SUM(assistance_releases.amount) as total')
            ->groupBy('assistance_programs.id', 'assistance_programs.name')
            ->pluck('total', 'name');

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recent' => CamData::paginator($recent, fn ($a) => CamData::applicationRow($a)),
            'chartMonth' => ['labels' => $byMonth->keys()->values(), 'data' => $byMonth->values()->map(fn ($v) => (int) $v)->values()],
            'chartProgram' => ['labels' => $byProgram->keys()->values(), 'data' => $byProgram->values()->map(fn ($v) => (int) $v)->values()],
            'chartType' => [
                'labels' => $byType->keys()->map(fn ($k) => str_replace('_', ' ', (string) $k))->values(),
                'data' => $byType->values()->map(fn ($v) => (int) $v)->values(),
            ],
            'chartDecision' => ['labels' => array_keys($approvedRejected), 'data' => array_values($approvedRejected)],
            'chartAmount' => ['labels' => $amountByProgram->keys()->values(), 'data' => $amountByProgram->values()->map(fn ($v) => (float) $v)->values()],
        ]);
    }
}
