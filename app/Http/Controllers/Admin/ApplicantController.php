<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\AuditService;
use App\Support\CamData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantController extends Controller
{
    public function index(Request $request): Response
    {
        $applicants = Applicant::query()
            ->with(['profile', 'primaryAddress', 'user'])
            ->withCount('applications')
            ->when($request->filled('type'), fn ($q) => $q->whereHas('profile', fn ($p) => $p->where('beneficiary_type', $request->string('type'))))
            ->when($request->filled('barangay'), fn ($q) => $q->whereHas('primaryAddress', fn ($a) => $a->where('barangay', $request->string('barangay'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = '%'.$request->string('q').'%';
                $q->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', $search)
                        ->orWhere('applicant_no', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Applicants/Index', [
            'applicants' => CamData::paginator($applicants, fn ($a) => [
                ...CamData::applicant($a),
                'can_delete' => ($a->applications_count ?? 0) === 0,
            ]),
            'barangays' => config('cams.barangays'),
            'filters' => $request->only(['type', 'barangay', 'q']),
        ]);
    }

    public function show(Applicant $applicant): Response
    {
        $applicant->load(['profile', 'primaryAddress', 'user']);

        $applications = $applicant->applications()
            ->with(['program.category'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Applicants/Show', [
            'applicant' => CamData::applicant($applicant),
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
        ]);
    }

    public function destroy(Applicant $applicant, AuditService $audit): RedirectResponse
    {
        if ($applicant->applications()->exists()) {
            return back()->with('error', 'This beneficiary cannot be deleted while application records still exist.');
        }

        $user = $applicant->user;
        $label = $applicant->full_name.' ('.$applicant->applicant_no.')';

        $user?->delete();

        $audit->log('deleted', 'Deleted beneficiary record '.$label);

        return redirect()->route('admin.applicants.index')->with('success', 'Beneficiary record deleted.');
    }
}
