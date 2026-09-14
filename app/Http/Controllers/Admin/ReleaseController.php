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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    public function index(Request $request): Response
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
            ->paginate(15)
            ->withQueryString();

        $approved = Application::query()
            ->with(['applicant', 'program'])
            ->where('status', ApplicationStatus::Approved->value)
            ->tap(fn (Builder $query) => $this->applyReleaseFilters($query, $filters))
            ->orderBy('application_no')
            ->get();

        $forRelease = Application::query()
            ->with(['applicant', 'program', 'latestSchedule'])
            ->where('status', ApplicationStatus::ScheduledForRelease->value)
            ->tap(fn (Builder $query) => $this->applyReleaseFilters($query, $filters))
            ->orderBy('application_no')
            ->get();

        return Inertia::render('Admin/Releases/Index', [
            'schedules' => CamData::paginator($schedules, fn ($s) => CamData::schedule($s)),
            'approved' => $approved->map(fn ($a) => CamData::applicationRow($a))->values(),
            'forRelease' => $forRelease->map(fn ($a) => CamData::applicationRow($a))->values(),
            'programs' => AssistanceProgram::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'methods' => config('cams.release_methods'),
        ]);
    }

    public function released(): Response
    {
        $releases = AssistanceRelease::query()
            ->with(['application.applicant', 'application.program', 'officer'])
            ->latest('released_at')
            ->paginate(15);

        return Inertia::render('Admin/Releases/Released', [
            'releases' => CamData::paginator($releases, fn ($r) => CamData::release($r)),
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

    public function verifyForm(): Response
    {
        return Inertia::render('Admin/Releases/Verify', ['result' => null]);
    }

    public function verify(Request $request, ReleaseService $releases): Response
    {
        $data = $request->validate([
            'lookup' => ['required', 'string'],
            'method' => ['required', 'in:application_no,reference_no,qr'],
        ]);

        $raw = $releases->verifyClaim($data['lookup'], $request->user(), $data['method']);
        $release = $raw['release'] ?? null;

        return Inertia::render('Admin/Releases/Verify', [
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
}
