<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\DocumentSubmission;
use App\Services\ApplicationService;
use App\Support\CamData;
use App\Support\DocumentFiles;
use App\Support\ProgramFormFieldRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applications) {}

    public function index(Request $request): Response
    {
        $applications = $request->user()->applicant->applications()
            ->with(['program.category', 'program.formFields', 'program.requirements', 'answers', 'documents.verification'])
            ->latest()
            ->paginate(12);

        return Inertia::render('Applicant/Applications/Index', [
            'applications' => CamData::paginator($applications, fn ($a) => CamData::applicationRow($a)),
        ]);
    }

    public function show(Request $request, Application $application): Response
    {
        $this->authorize('view', $application);
        $this->applications->returnToVerificationAfterReplacement($application, $request->user(), onlyWhenAllReplaced: true);
        $application->load([
            'program.category', 'program.requirements', 'program.formFields', 'answers',
            'documents.verification', 'documents.ocrResult.fields',
            'statusHistory.user', 'latestSchedule', 'latestRelease', 'latestEvaluation', 'latestApproval',
            'applicant.profile', 'applicant.primaryAddress',
        ]);

        return Inertia::render('Applicant/Applications/Show', [
            'application' => CamData::applicationDetail($application),
        ]);
    }

    public function start(AssistanceProgram $program): RedirectResponse
    {
        return redirect()->route('site.apply.create', $program);
    }

    public function eligibility(Request $request, Application $application): Response
    {
        $this->authorize('update', $application);
        $application->load(['program.eligibilityRules', 'program.category', 'program.requirements', 'program.formFields']);

        return Inertia::render('Applicant/Apply/Eligibility', [
            'application' => CamData::applicationDetail($application),
        ]);
    }

    public function storeEligibility(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);
        $request->validate([
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'You must confirm that you meet the eligibility conditions before continuing.',
        ]);

        $this->applications->confirmEligibility($application, $request->user());

        return redirect()->route('applicant.apply.form', $application);
    }

    public function form(Application $application): Response|RedirectResponse
    {
        $this->authorize('view', $application);

        if (! $application->canBeEditedByApplicant()) {
            return redirect()->route('applicant.applications.show', $application);
        }

        $application->load(['program.formFields', 'program.category', 'program.requirements', 'answers']);

        if ($application->program->formFields->isEmpty()) {
            return redirect()->route('applicant.apply.documents', $application);
        }

        return Inertia::render('Applicant/Apply/Form', [
            'application' => CamData::applicationDetail($application),
            'fields' => CamData::program($application->program, true)['form_fields'],
            'answers' => $application->answerMap(),
        ]);
    }

    public function storeForm(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);
        $application->load(['program.formFields', 'applicant.profile']);

        if ($application->program->formFields->isEmpty()) {
            return redirect()->route('applicant.apply.documents', $application);
        }

        $beneficiaryType = $application->applicant?->beneficiaryType()?->value;
        $data = $request->validate(
            ProgramFormFieldRules::rules($application->program, '', $beneficiaryType),
            ProgramFormFieldRules::messages($application->program, ''),
        );

        if ($beneficiaryType === 'non_student') {
            foreach (ProgramFormFieldRules::studentFieldNames() as $fieldName) {
                $data[$fieldName] = null;
            }
        }

        $this->applications->saveAnswers($application, $data, $request->user());

        return redirect()->route('applicant.apply.documents', $application)->with('success', 'Application form saved.');
    }

    public function documents(Request $request, Application $application): Response|RedirectResponse
    {
        $this->authorize('view', $application);

        if (! $application->canSupplyMissingDocuments()) {
            return redirect()->route('applicant.applications.show', $application);
        }

        $application->load(['program.requirements', 'program.formFields', 'program.category', 'documents.verification', 'documents.ocrResult.fields', 'answers']);

        return Inertia::render('Applicant/Apply/Documents', [
            'application' => CamData::applicationDetail($application),
        ]);
    }

    public function storeDocument(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_unless(
            $request->user()->can('update', $application) || $application->canSupplyMissingDocuments(),
            403,
        );
        $data = $request->validate([
            'requirement_id' => ['required', 'integer'],
            'side' => ['nullable', 'in:front,back'],
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $this->applications->storeDocument(
            $application,
            (int) $data['requirement_id'],
            $request->file('file'),
            $request->user(),
            $data['side'] ?? 'front',
        );

        $application->refresh();

        if ($application->documentsNeedingAction()->isNotEmpty()) {
            return back()->with('success', 'Replacement uploaded. The application is now under verification. Replace any remaining files marked for revision.');
        }

        if ($application->status === ApplicationStatus::UnderVerification) {
            return redirect()
                ->route('applicant.applications.show', $application)
                ->with('success', 'Replacement uploaded. The application is now under verification.');
        }

        return back()->with('success', 'Document uploaded.');
    }

    public function review(Request $request, Application $application): Response|RedirectResponse
    {
        $this->authorize('view', $application);

        if (! $application->canBeEditedByApplicant()) {
            return redirect()->route('applicant.applications.show', $application);
        }

        $application->load(['program.requirements', 'program.formFields', 'program.category', 'answers', 'documents.verification', 'documents.ocrResult.fields', 'applicant.profile', 'applicant.primaryAddress']);

        return Inertia::render('Applicant/Apply/Review', [
            'application' => CamData::applicationDetail($application),
        ]);
    }

    public function submit(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);
        $this->applications->submit($application, $request->user());

        return redirect()
            ->route('applicant.applications.show', $application)
            ->with('success', 'Application '.$application->application_no.' has been submitted.');
    }

    public function download(Request $request, Application $application, DocumentSubmission $document): StreamedResponse
    {
        $this->authorize('view', $application);
        abort_unless($document->application_id === $application->id, 404);

        return DocumentFiles::download($document->file_path, $document->original_name);
    }

    public function preview(Request $request, Application $application, DocumentSubmission $document): StreamedResponse
    {
        $this->authorize('view', $application);
        abort_unless($document->application_id === $application->id, 404);

        return DocumentFiles::inline($document->file_path, $document->original_name);
    }
}
