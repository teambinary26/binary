<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\AssistanceRelease;
use App\Support\CamData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function applications(Request $request): Response|StreamedResponse
    {
        $query = Application::query()
            ->with(['applicant.primaryAddress', 'program.category'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('date_to')))
            ->when($request->filled('program'), fn ($q) => $q->where('assistance_program_id', $request->integer('program')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('barangay'), fn ($q) => $q->whereHas('applicant.primaryAddress', fn ($a) => $a->where('barangay', $request->string('barangay'))))
            ->when($request->filled('beneficiary'), fn ($q) => $q->whereHas('applicant.profile', fn ($p) => $p->where('beneficiary_type', $request->string('beneficiary'))))
            ->latest('submitted_at');

        if ($request->string('export') === 'csv') {
            return $this->csv('application-report.csv', $query->get(), [
                'Application No.', 'Applicant', 'Program', 'Barangay', 'Status', 'Date Applied', 'Amount',
            ], function (Application $row) {
                return [
                    $row->application_no,
                    $row->applicant->full_name,
                    $row->program->name,
                    $row->applicant->primaryAddress?->barangay,
                    $row->status->label(),
                    gov_date($row->submitted_at),
                    $row->approved_amount,
                ];
            });
        }

        $applications = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Reports/Applications', [
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
            'programs' => AssistanceProgram::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => CamData::statuses(),
            'barangays' => config('cams.barangays'),
            'filters' => $request->only(['date_from', 'date_to', 'program', 'status', 'barangay', 'beneficiary']),
            'generated_at' => gov_datetime(now()),
        ]);
    }

    public function financial(Request $request): Response|StreamedResponse
    {
        $approved = Application::query()->whereIn('status', [
            ApplicationStatus::Approved->value,
            ApplicationStatus::ScheduledForRelease->value,
            ApplicationStatus::Released->value,
            ApplicationStatus::Completed->value,
        ])->sum('approved_amount');

        $released = AssistanceRelease::query()
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('released_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('released_at', '<=', $request->date('date_to')))
            ->sum('amount');

        $pending = Application::query()->whereIn('status', [
            ApplicationStatus::Approved->value,
            ApplicationStatus::ScheduledForRelease->value,
        ])->sum('approved_amount');

        $byProgram = AssistanceRelease::query()
            ->join('applications', 'applications.id', '=', 'assistance_releases.application_id')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'applications.assistance_program_id')
            ->selectRaw('assistance_programs.name as name, SUM(assistance_releases.amount) as total, COUNT(*) as count')
            ->groupBy('assistance_programs.id', 'assistance_programs.name')
            ->get();

        if ($request->string('export') === 'csv') {
            return $this->csv('financial-report.csv', $byProgram, ['Program', 'Releases', 'Amount'], fn ($row) => [
                $row->name, $row->count, $row->total,
            ]);
        }

        return Inertia::render('Admin/Reports/Financial', [
            'approved' => peso($approved, false),
            'released' => peso($released, false),
            'pending' => peso($pending, false),
            'byProgram' => $byProgram->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'total' => peso($row->total),
            ])->values(),
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }

    public function beneficiaries(Request $request): Response|StreamedResponse
    {
        $byBarangay = Applicant::query()
            ->join('applicant_addresses', 'applicant_addresses.applicant_id', '=', 'applicants.id')
            ->where('applicant_addresses.is_primary', true)
            ->selectRaw('barangay, COUNT(*) as total')
            ->groupBy('barangay')
            ->orderBy('barangay')
            ->get();

        $byType = Applicant::query()
            ->join('applicant_profiles', 'applicant_profiles.applicant_id', '=', 'applicants.id')
            ->selectRaw('beneficiary_type, COUNT(*) as total')
            ->groupBy('beneficiary_type')
            ->get();

        $bySex = Applicant::query()->selectRaw('sex, COUNT(*) as total')->groupBy('sex')->get();

        if ($request->string('export') === 'csv') {
            return $this->csv('beneficiary-report.csv', $byBarangay, ['Barangay', 'Applicants'], fn ($row) => [
                $row->barangay, $row->total,
            ]);
        }

        return Inertia::render('Admin/Reports/Beneficiaries', [
            'byBarangay' => $byBarangay->map(fn ($row) => [
                'barangay' => $row->barangay,
                'total' => (int) $row->total,
            ])->values(),
            'byType' => $byType->map(fn ($row) => [
                'type' => str_replace('_', ' ', ucfirst((string) $row->beneficiary_type)),
                'total' => (int) $row->total,
            ])->values(),
            'bySex' => $bySex->map(fn ($row) => [
                'sex' => ucfirst((string) $row->sex),
                'total' => (int) $row->total,
            ])->values(),
        ]);
    }

    private function csv(string $filename, $rows, array $headers, callable $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers, $map) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $map($row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
