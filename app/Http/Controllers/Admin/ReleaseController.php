<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\AssistanceRelease;
use App\Models\ReleaseSchedule;
use App\Services\ReleaseService;
use App\Support\CamData;
use App\Support\ExcelWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReleaseController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'program' => $request->integer('program') ?: '',
            'release_date' => trim((string) $request->string('release_date')),
            'schedule_status' => trim((string) $request->string('schedule_status')),
        ];

        $schedules = ReleaseSchedule::query()
            ->with(['application.applicant', 'application.program.category'])
            ->whereHas('application', fn (Builder $query) => $this->applyReleaseFilters($query, $filters))
            ->when($filters['release_date'] !== '', fn (Builder $query) => $query->whereDate('release_date', $filters['release_date']))
            ->when(
                in_array($filters['schedule_status'], ['scheduled', 'completed'], true),
                fn (Builder $query) => $query->where('status', $filters['schedule_status'])
            )
            ->latest('release_date')
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        $approved = Application::query()
            ->with(['applicant', 'program'])
            ->where('status', ApplicationStatus::Approved->value)
            ->tap(fn (Builder $query) => $this->applyReleaseFilters($query, $filters))
            ->orderBy('application_no')
            ->paginate(15, ['*'], 'approved_page')
            ->withQueryString();

        $forRelease = Application::query()
            ->with(['applicant', 'program', 'latestSchedule'])
            ->where('status', ApplicationStatus::ScheduledForRelease->value)
            ->tap(fn (Builder $query) => $this->applyReleaseFilters($query, $filters))
            ->orderBy('application_no')
            ->get();

        return Inertia::render('Admin/Releases/Index', [
            'schedules' => CamData::paginator($schedules, fn ($s) => CamData::schedule($s)),
            'approved' => CamData::paginator($approved, fn ($a) => CamData::applicationRow($a)),
            'forRelease' => $forRelease->map(fn ($a) => CamData::applicationRow($a))->values(),
            'programs' => AssistanceProgram::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'methods' => config('cams.release_methods'),
        ]);
    }

    public function released(Request $request): InertiaResponse|Response
    {
        $filters = $this->releasedFilters($request);
        $query = $this->releasedQuery($filters);

        if ($request->input('export') === 'excel') {
            return $this->exportReleasedExcel($query->get());
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportReleasedPdf($query->get(), $filters);
        }

        $releases = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Releases/Released', [
            'releases' => CamData::paginator($releases, fn ($r) => CamData::release($r)),
            'programs' => AssistanceProgram::query()->orderBy('name')->get(['id', 'name']),
            'barangays' => config('cams.barangays'),
            'filters' => $filters,
            'generated_at' => gov_datetime(now()),
        ]);
    }

    public function schedule(Request $request, ReleaseService $releases): RedirectResponse
    {
        $data = $request->validate([
            'application_id' => ['nullable', 'integer', 'exists:applications,id', 'required_without:application_ids'],
            'application_ids' => ['nullable', 'array', 'min:1', 'required_without:application_id'],
            'application_ids.*' => ['integer', 'exists:applications,id'],
            'release_date' => ['required', 'date'],
            'release_location' => ['required', 'string', 'max:255'],
            'release_method' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $ids = collect($data['application_ids'] ?? [])
            ->push($data['application_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($ids, $releases, $request, $data) {
            foreach ($ids as $id) {
                $application = Application::query()->findOrFail($id);
                $releases->schedule($application, $request->user(), $data);
            }
        });

        $count = $ids->count();

        return back()->with('success', $count === 1
            ? 'Release has been scheduled.'
            : $count.' releases have been scheduled.');
    }

    public function reschedule(Request $request, ReleaseSchedule $schedule, ReleaseService $releases): RedirectResponse
    {
        $data = $request->validate([
            'release_date' => ['required', 'date'],
            'release_location' => ['required', 'string', 'max:255'],
            'release_method' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $releases->reschedule($schedule, $request->user(), $data);

        return back()->with('success', 'Release has been rescheduled.');
    }

    public function rescheduleMany(Request $request, ReleaseService $releases): RedirectResponse
    {
        $data = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer', 'exists:release_schedules,id'],
            'release_date' => ['required', 'date'],
            'release_location' => ['required', 'string', 'max:255'],
            'release_method' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $ids = collect($data['schedule_ids'])->unique()->values();

        DB::transaction(function () use ($ids, $releases, $request, $data) {
            foreach ($ids as $id) {
                $schedule = ReleaseSchedule::query()->findOrFail($id);
                $releases->reschedule($schedule, $request->user(), $data);
            }
        });

        $count = $ids->count();

        return back()->with('success', $count === 1
            ? 'Release has been rescheduled.'
            : $count.' releases have been rescheduled.');
    }

    public function record(Request $request, ReleaseService $releases): RedirectResponse
    {
        $data = $request->validate([
            'application_id' => ['required', 'exists:applications,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $application = Application::query()->findOrFail($data['application_id']);
        $releases->recordRelease($application, $request->user(), $data);

        return back()->with('success', 'Assistance release recorded.');
    }

    public function verifyForm(): InertiaResponse
    {
        return Inertia::render('Admin/Releases/Verify', [
            'result' => null,
            'filters' => [
                'method' => 'application_no',
                'lookup' => '',
            ],
        ]);
    }

    public function verify(Request $request, ReleaseService $releases): InertiaResponse
    {
        $data = $request->validate([
            'lookup' => ['required', 'string'],
            'method' => ['required', 'in:application_no,reference_no,qr'],
        ]);

        $lookup = trim($data['lookup']);
        $raw = $releases->verifyClaim($lookup, $request->user(), $data['method']);
        $release = $raw['release'] ?? null;

        if ($release) {
            $release->loadMissing(['application.applicant', 'application.program', 'officer']);
        }

        return Inertia::render('Admin/Releases/Verify', [
            'filters' => [
                'method' => $data['method'],
                'lookup' => $lookup,
            ],
            'result' => [
                'valid' => $raw['valid'],
                'result' => $raw['result'],
                'message' => $raw['message'],
                'release' => $release ? array_merge(CamData::release($release), [
                    'status' => $release->application->status->value,
                    'status_label' => $release->application->status->label(),
                    'status_tone' => $release->application->status->tone(),
                    'program_name' => $release->application->program->name,
                    'applicant_name' => $release->application->applicant->full_name,
                ]) : null,
            ],
        ]);
    }

    private function applyReleaseFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['program'] !== '' && $filters['program'] !== null, function (Builder $query) use ($filters) {
                $query->where('assistance_program_id', $filters['program']);
            })
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $search = '%'.$filters['q'].'%';
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('application_no', 'like', $search)
                        ->orWhereHas('applicant', fn (Builder $applicant) => $applicant->where('full_name', 'like', $search));
                });
            });
    }

    /**
     * @return array{q: string, program: int|string, date_from: string, date_to: string, barangay: string}
     */
    private function releasedFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->string('q')),
            'program' => $request->integer('program') ?: '',
            'date_from' => trim((string) $request->string('date_from')),
            'date_to' => trim((string) $request->string('date_to')),
            'barangay' => trim((string) $request->string('barangay')),
        ];
    }

    /**
     * @param  array{q: string, program: int|string, date_from: string, date_to: string, barangay: string}  $filters
     */
    private function releasedQuery(array $filters): Builder
    {
        return AssistanceRelease::query()
            ->with(['application.applicant.primaryAddress', 'application.program', 'officer'])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $search = '%'.$filters['q'].'%';
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('reference_no', 'like', $search)
                        ->orWhere('verification_code', 'like', $search)
                        ->orWhereHas('application', function (Builder $application) use ($search) {
                            $application->where('application_no', 'like', $search)
                                ->orWhereHas('applicant', fn (Builder $applicant) => $applicant->where('full_name', 'like', $search));
                        });
                });
            })
            ->when($filters['program'] !== '' && $filters['program'] !== null, function (Builder $query) use ($filters) {
                $query->whereHas('application', fn (Builder $application) => $application->where('assistance_program_id', $filters['program']));
            })
            ->when($filters['date_from'] !== '', fn (Builder $query) => $query->whereDate('released_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn (Builder $query) => $query->whereDate('released_at', '<=', $filters['date_to']))
            ->when($filters['barangay'] !== '', function (Builder $query) use ($filters) {
                $query->whereHas(
                    'application.applicant.primaryAddress',
                    fn (Builder $address) => $address->where('barangay', $filters['barangay'])
                );
            })
            ->latest('released_at');
    }

    private function exportReleasedExcel($releases): Response
    {
        $rows = $releases->map(fn (AssistanceRelease $release) => [
            $release->reference_no,
            $release->application?->application_no,
            $release->application?->applicant?->full_name,
            $release->application?->program?->name,
            (float) $release->amount,
            gov_datetime($release->released_at),
            $release->officer?->name,
            $release->verification_code,
        ])->all();

        return ExcelWorkbook::download('released-assistance.xls', [
            'Reference no.',
            'Application',
            'Applicant',
            'Program',
            'Amount',
            'Released',
            'Officer',
            'Verification code',
        ], $rows, 'Released Assistance');
    }

    private function exportReleasedPdf($releases, array $filters): Response
    {
        $summary = collect([
            $filters['q'] !== '' ? 'Search: '.$filters['q'] : null,
            $filters['program'] !== '' ? 'Program ID: '.$filters['program'] : null,
            $filters['date_from'] !== '' ? 'From '.$filters['date_from'] : null,
            $filters['date_to'] !== '' ? 'To '.$filters['date_to'] : null,
            $filters['barangay'] !== '' ? 'Barangay '.$filters['barangay'] : null,
        ])->filter()->implode(' · ');

        $programName = $filters['program'] !== ''
            ? AssistanceProgram::query()->whereKey($filters['program'])->value('name')
            : null;
        if ($programName) {
            $summary = str_replace('Program ID: '.$filters['program'], 'Program: '.$programName, $summary);
        }

        return Pdf::loadView('exports.released-assistance', [
            'title' => 'Disbursement Register — Released Assistance',
            'releases' => $releases,
            'total' => $releases->sum('amount'),
            'generated_at' => gov_datetime(now()),
            'filter_summary' => $summary,
        ])->setPaper('a4', 'landscape')->download('released-assistance.pdf');
    }
}
