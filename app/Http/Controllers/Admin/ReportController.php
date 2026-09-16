<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\AssistanceRelease;
use App\Support\CamData;
use App\Support\ExcelWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function applications(Request $request): InertiaResponse|Response
    {
        $filters = [
            'date_from' => trim((string) $request->string('date_from')),
            'date_to' => trim((string) $request->string('date_to')),
            'program' => $request->integer('program') ?: '',
            'status' => trim((string) $request->string('status')),
            'barangay' => trim((string) $request->string('barangay')),
            'beneficiary' => trim((string) $request->string('beneficiary')),
        ];

        $query = Application::query()
            ->with(['applicant.primaryAddress', 'program.category'])
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('submitted_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('submitted_at', '<=', $filters['date_to']))
            ->when($filters['program'] !== '', fn ($q) => $q->where('assistance_program_id', $filters['program']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['barangay'] !== '', fn ($q) => $q->whereHas('applicant.primaryAddress', fn ($a) => $a->where('barangay', $filters['barangay'])))
            ->when($filters['beneficiary'] !== '', fn ($q) => $q->whereHas('applicant.profile', fn ($p) => $p->where('beneficiary_type', $filters['beneficiary'])))
            ->latest('submitted_at');

        if ($request->input('export') === 'excel') {
            return $this->exportApplicationsExcel($query->get());
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportApplicationsPdf($query->get(), $filters);
        }

        $applications = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Reports/Applications', [
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
            'programs' => AssistanceProgram::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => CamData::statuses(),
            'barangays' => config('cams.barangays'),
            'filters' => $filters,
            'generated_at' => gov_datetime(now()),
        ]);
    }

    public function financial(Request $request): InertiaResponse|Response
    {
        $filters = [
            'date_from' => trim((string) $request->string('date_from')),
            'date_to' => trim((string) $request->string('date_to')),
        ];

        $approved = Application::query()->whereIn('status', [
            ApplicationStatus::Approved->value,
            ApplicationStatus::ScheduledForRelease->value,
            ApplicationStatus::Released->value,
            ApplicationStatus::Completed->value,
        ])->sum('approved_amount');

        $released = AssistanceRelease::query()
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('released_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('released_at', '<=', $filters['date_to']))
            ->sum('amount');

        $pending = Application::query()->whereIn('status', [
            ApplicationStatus::Approved->value,
            ApplicationStatus::ScheduledForRelease->value,
        ])->sum('approved_amount');

        $byProgramQuery = AssistanceRelease::query()
            ->join('applications', 'applications.id', '=', 'assistance_releases.application_id')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'applications.assistance_program_id')
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('assistance_releases.released_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('assistance_releases.released_at', '<=', $filters['date_to']))
            ->selectRaw('assistance_programs.name as name, SUM(assistance_releases.amount) as total, COUNT(*) as count')
            ->groupBy('assistance_programs.id', 'assistance_programs.name')
            ->orderBy('assistance_programs.name');

        $byProgram = $byProgramQuery->get();

        if ($request->input('export') === 'excel') {
            return $this->exportFinancialExcel($byProgram, $approved, $released, $pending);
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportFinancialPdf($byProgram, $approved, $released, $pending, $filters);
        }

        return Inertia::render('Admin/Reports/Financial', [
            'approved' => peso($approved, false),
            'released' => peso($released, false),
            'pending' => peso($pending, false),
            'byProgram' => CamData::paginateCollection($byProgram, fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'total' => peso($row->total),
            ]),
            'filters' => $filters,
            'generated_at' => gov_datetime(now()),
        ]);
    }

    public function beneficiaries(Request $request): InertiaResponse|StreamedResponse
    {
        $byBarangayQuery = Applicant::query()
            ->join('applicant_addresses', 'applicant_addresses.applicant_id', '=', 'applicants.id')
            ->where('applicant_addresses.is_primary', true)
            ->selectRaw('barangay, COUNT(*) as total')
            ->groupBy('barangay')
            ->orderBy('barangay');

        $byTypeQuery = Applicant::query()
            ->join('applicant_profiles', 'applicant_profiles.applicant_id', '=', 'applicants.id')
            ->selectRaw('beneficiary_type, COUNT(*) as total')
            ->groupBy('beneficiary_type')
            ->orderBy('beneficiary_type');

        $bySexQuery = Applicant::query()
            ->selectRaw('sex, COUNT(*) as total')
            ->groupBy('sex')
            ->orderBy('sex');

        if ($request->string('export') === 'csv') {
            return $this->csv('beneficiary-report.csv', $byBarangayQuery->get(), ['Barangay', 'Applicants'], fn ($row) => [
                $row->barangay, $row->total,
            ]);
        }

        return Inertia::render('Admin/Reports/Beneficiaries', [
            'byBarangay' => CamData::paginateCollection($byBarangayQuery->get(), fn ($row) => [
                'barangay' => $row->barangay,
                'total' => (int) $row->total,
            ], 15, 'barangay_page'),
            'byType' => CamData::paginateCollection($byTypeQuery->get(), fn ($row) => [
                'type' => str_replace('_', ' ', ucfirst((string) $row->beneficiary_type)),
                'total' => (int) $row->total,
            ], 15, 'type_page'),
            'bySex' => CamData::paginateCollection($bySexQuery->get(), fn ($row) => [
                'sex' => ucfirst((string) $row->sex),
                'total' => (int) $row->total,
            ], 15, 'sex_page'),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Application>  $applications
     */
    private function exportApplicationsExcel($applications): Response
    {
        $rows = $applications->map(fn (Application $row) => [
            $row->application_no,
            $row->applicant?->full_name,
            $row->program?->name,
            $row->applicant?->primaryAddress?->barangay,
            $row->status->label(),
            gov_date($row->submitted_at ?? $row->created_at),
            $row->approved_amount !== null ? (float) $row->approved_amount : '',
        ])->all();

        return ExcelWorkbook::download('application-report.xls', [
            'Application no.',
            'Applicant',
            'Program',
            'Barangay',
            'Status',
            'Date applied',
            'Amount',
        ], $rows, 'Application Report');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Application>  $applications
     * @param  array{date_from: string, date_to: string, program: int|string, status: string, barangay: string, beneficiary: string}  $filters
     */
    private function exportApplicationsPdf($applications, array $filters): Response
    {
        $statusLabel = $filters['status'] !== ''
            ? (ApplicationStatus::tryFrom($filters['status'])?->label() ?: $filters['status'])
            : null;
        $programName = $filters['program'] !== ''
            ? AssistanceProgram::query()->whereKey($filters['program'])->value('name')
            : null;
        $beneficiaryLabel = match ($filters['beneficiary']) {
            'student' => 'Student',
            'non_student' => 'Non-student',
            default => null,
        };

        $summary = collect([
            $filters['date_from'] !== '' ? 'From '.$filters['date_from'] : null,
            $filters['date_to'] !== '' ? 'To '.$filters['date_to'] : null,
            $programName ? 'Program: '.$programName : null,
            $statusLabel ? 'Status: '.$statusLabel : null,
            $filters['barangay'] !== '' ? 'Barangay '.$filters['barangay'] : null,
            $beneficiaryLabel ? 'Beneficiary: '.$beneficiaryLabel : null,
        ])->filter()->implode(' · ');

        return Pdf::loadView('exports.application-report', [
            'title' => 'Application Report',
            'applications' => $applications,
            'total' => $applications->sum('approved_amount'),
            'generated_at' => gov_datetime(now()),
            'filter_summary' => $summary,
        ])->setPaper('a4', 'landscape')->download('application-report.pdf');
    }

    private function exportFinancialExcel($rows, $approved, $released, $pending): Response
    {
        $excelRows = collect([
            ['Total approved', '', (float) $approved],
            ['Total released', '', (float) $released],
            ['Total pending release', '', (float) $pending],
            ['', '', ''],
            ['Program', 'No. of releases', 'Amount by program'],
        ])->concat($rows->map(fn ($row) => [
            $row->name,
            (int) $row->count,
            (float) $row->total,
        ]))->push([
            'Total',
            (int) $rows->sum('count'),
            (float) $rows->sum('total'),
        ])->all();

        return ExcelWorkbook::download('financial-report.xls', [
            'Summary / Program',
            'Releases',
            'Amount',
        ], $excelRows, 'Financial Report');
    }

    /**
     * @param  array{date_from: string, date_to: string}  $filters
     */
    private function exportFinancialPdf($rows, $approved, $released, $pending, array $filters): Response
    {
        $summary = collect([
            $filters['date_from'] !== '' ? 'From '.$filters['date_from'] : null,
            $filters['date_to'] !== '' ? 'To '.$filters['date_to'] : null,
        ])->filter()->implode(' · ');

        return Pdf::loadView('exports.financial-report', [
            'title' => 'Financial Report',
            'rows' => $rows,
            'approved' => peso($approved, false),
            'released' => peso($released, false),
            'pending' => peso($pending, false),
            'generated_at' => gov_datetime(now()),
            'filter_summary' => $summary,
        ])->setPaper('a4', 'portrait')->download('financial-report.pdf');
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
