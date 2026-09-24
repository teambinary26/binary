<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\User;
use App\Services\EligibilityAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('evaluation page includes an ocr eligibility assessment before save', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->where('status', ApplicationStatus::UnderEvaluation)
        ->has('documents')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Applications/Show')
            ->has('application.eligibility_assessment')
            ->has('application.eligibility_assessment.status')
            ->has('application.eligibility_assessment.rules')
            ->has('application.eligibility_assessment.sources')
            ->has('application.program_detail.eligibility_rules')
        );
});

test('admin can save program eligibility rules that ocr will check', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $program = AssistanceProgram::query()->with(['category', 'requirements', 'formFields', 'eligibilityRules'])->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.programs.edit', $program))
        ->put(route('admin.programs.update', $program), [
            'program_category_id' => $program->program_category_id,
            'name' => $program->name,
            'code' => $program->code,
            'description' => $program->description,
            'eligibility' => $program->eligibility,
            'beneficiary_type' => $program->beneficiary_type->value,
            'amount_type' => $program->amount_type,
            'amount' => $program->amount,
            'amount_max' => $program->amount_max,
            'is_open' => true,
            'rules' => [
                [
                    'label' => 'Resident of Nabua',
                    'check_mode' => 'ocr',
                    'field' => 'address',
                    'operator' => 'contains',
                    'value' => 'Nabua',
                ],
                [
                    'label' => 'Currently enrolled',
                    'check_mode' => 'ocr',
                    'field' => 'document_text',
                    'operator' => 'contains',
                    'value' => 'enrolled',
                ],
                [
                    'label' => 'Has not received the same program for the current academic year',
                    'check_mode' => 'manual',
                ],
            ],
        ])
        ->assertRedirect();

    $program->refresh()->load('eligibilityRules');

    expect($program->eligibilityRules)->toHaveCount(3)
        ->and($program->eligibilityRules->first()->field)->toBe('address')
        ->and($program->eligibilityRules->first()->operator)->toBe('contains')
        ->and($program->eligibilityRules->first()->value)->toBe('Nabua')
        ->and($program->eligibilityRules->first()->check_mode)->toBe('ocr')
        ->and($program->eligibilityRules[1]->field)->toBe('document_text')
        ->and($program->eligibilityRules[1]->value)->toBe('enrolled')
        ->and($program->eligibilityRules->last()->check_mode)->toBe('manual')
        ->and($program->eligibilityRules->last()->field)->toBeNull();
});

test('evaluator can scan verification documents for eligibility before saving', function () {
    Http::fake([
        'https://api.ocr.space/*' => Http::response([
            'OCRExitCode' => 1,
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [['ParsedText' => 'Resident of Nabua. Currently enrolled.']],
        ], 200),
    ]);

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = evaluationApplication();

    foreach ($application->documents as $document) {
        $document->ocrResult()?->delete();
    }

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.scan-eligibility', $application))
        ->assertRedirect()
        ->assertSessionHas('success');

    $application->unsetRelation('documents');
    $application->load('documents.ocrResult');

    expect($application->documents->every(fn ($document) => filled($document->ocrResult?->raw_text)))->toBeTrue();
});

test('evaluation stores staff decisions for manual eligibility rules', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = evaluationApplication();
    setProgramRules($application, [
        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua', 'check_mode' => 'ocr'],
        ['label' => 'Has not received the same program for the current academic year', 'check_mode' => 'manual'],
    ]);

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.evaluate', $application), [
            'eligibility_passed' => true,
            'eligibility_checks' => [
                ['id' => $application->program->eligibilityRules[0]->id, 'label' => 'Resident of Nabua', 'check_mode' => 'ocr', 'status' => 'passed'],
                ['id' => $application->program->eligibilityRules[1]->id, 'label' => 'Has not received the same program for the current academic year', 'check_mode' => 'manual', 'status' => 'passed'],
            ],
            'documents_complete' => true,
            'assessment' => 'OCR confirmed residency. Staff confirmed no prior assistance this year.',
            'recommendation' => 'approval',
            'recommended_amount' => 2000,
            'remarks' => 'Eligible after OCR and manual check.',
        ])
        ->assertRedirect();

    $evaluation = $application->fresh()->latestEvaluation;

    expect($evaluation?->eligibility_passed)->toBeTrue()
        ->and($evaluation?->eligibility_checks)->toHaveCount(2)
        ->and($evaluation?->eligibility_checks[1]['check_mode'])->toBe('manual')
        ->and($evaluation?->eligibility_checks[1]['status'])->toBe('passed');
});

test('ocr text from verification documents can mark the applicant eligible', function () {
    $application = evaluationApplication();
    setProgramRules($application, [
        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
        ['label' => 'Currently enrolled', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'enrolled'],
    ]);
    writeDocumentOcr(
        $application,
        "Certificate of Enrollment\n{$application->applicant->full_name} is currently enrolled.\nAddress: Poblacion, Nabua, Camarines Sur"
    );

    $result = app(EligibilityAssessmentService::class)->assess($application);

    expect($result['status'])->toBe('eligible')
        ->and($result['eligible'])->toBeTrue()
        ->and($result['passed_count'])->toBe(2)
        ->and($result['failed_count'])->toBe(0);
});

test('missing required ocr wording marks the applicant not eligible', function () {
    $application = evaluationApplication();
    setProgramRules($application, [
        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
    ]);
    writeDocumentOcr($application, 'Certificate of Enrollment for a student in Iriga City. Currently enrolled.');

    $result = app(EligibilityAssessmentService::class)->assess($application);

    expect($result['status'])->toBe('not_eligible')
        ->and($result['eligible'])->toBeFalse()
        ->and($result['failed_count'])->toBe(1)
        ->and($result['rules'][0]['status'])->toBe('failed');
});

test('label only rules can be inferred from scanned text', function () {
    $application = evaluationApplication();
    setProgramRules($application, [
        ['label' => 'Bona fide resident of the Municipality of Nabua'],
        ['label' => 'Currently enrolled student'],
    ]);
    writeDocumentOcr(
        $application,
        'Barangay certificate: resident of Nabua. Certificate of Enrollment: currently enrolled at Nabua National High School.'
    );

    $result = app(EligibilityAssessmentService::class)->assess($application);

    expect($result['status'])->toBe('eligible')
        ->and($result['rules'])->toHaveCount(2)
        ->and(collect($result['rules'])->every(fn (array $rule) => $rule['status'] === 'passed'))->toBeTrue();
});

test('manual prior-assistance rules are left for staff and list earlier applications', function () {
    $application = evaluationApplication();
    setProgramRules($application, [
        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua', 'check_mode' => 'ocr'],
        ['label' => 'Has not received the same program for the current academic year', 'check_mode' => 'manual'],
    ]);
    writeDocumentOcr(
        $application,
        "Proof of residency\n{$application->applicant->full_name}\nAddress: Poblacion, Nabua, Camarines Sur"
    );

    $prior = $application->replicate(['application_no', 'status', 'submitted_at']);
    $prior->application_no = 'CAMS-2026-PRIOR01';
    $prior->status = ApplicationStatus::Released;
    $prior->submitted_at = now()->subMonths(2);
    $prior->save();

    $result = app(EligibilityAssessmentService::class)->assess($application->fresh([
        'program.eligibilityRules',
        'documents.ocrResult.fields',
        'documents.verification',
    ]));

    expect($result['rules'][0]['check_mode'])->toBe('ocr')
        ->and($result['rules'][0]['status'])->toBe('passed')
        ->and($result['rules'][1]['status'])->toBe('failed')
        ->and($result['rules'][1]['shows_prior_records'])->toBeTrue()
        ->and($result['rules'][1]['prior_records'])->toHaveCount(1)
        ->and($result['rules'][1]['prior_records'][0]['application_no'])->toBe('CAMS-2026-PRIOR01')
        ->and($result['step_one'])->not->toBeEmpty()
        ->and($result['status'])->toBe('not_eligible');
});

test('missing ocr text asks staff to scan before saving', function () {
    $application = evaluationApplication();
    setProgramRules($application, [
        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
    ]);

    foreach ($application->documents as $document) {
        $document->ocrResult()->delete();
    }
    $application->unsetRelation('documents');
    $application->load(['documents.ocrResult', 'documents.verification']);

    $result = app(EligibilityAssessmentService::class)->assess($application);

    expect($result['status'])->toBe('no_ocr')
        ->and($result['eligible'])->toBeNull();
});

function evaluationApplication(): Application
{
    return Application::query()
        ->where('status', ApplicationStatus::UnderEvaluation)
        ->has('documents')
        ->with(['program.eligibilityRules', 'documents.ocrResult.fields', 'documents.verification', 'applicant'])
        ->firstOrFail();
}

function setProgramRules(Application $application, array $rules): void
{
    $application->program->eligibilityRules()->delete();

    foreach ($rules as $index => $rule) {
        $application->program->eligibilityRules()->create([
            'label' => $rule['label'],
            'field' => $rule['field'] ?? null,
            'operator' => $rule['operator'] ?? null,
            'value' => $rule['value'] ?? null,
            'check_mode' => $rule['check_mode'] ?? 'ocr',
            'sort_order' => $index + 1,
        ]);
    }

    $application->unsetRelation('program');
    $application->load('program.eligibilityRules');
}

function writeDocumentOcr(Application $application, string $text): void
{
    foreach ($application->documents as $document) {
        $document->verification()->updateOrCreate(
            ['document_submission_id' => $document->id],
            ['status' => DocumentVerificationStatus::Verified]
        );

        $document->ocrResult()->updateOrCreate(
            ['document_submission_id' => $document->id],
            [
                'status' => 'processed',
                'raw_text' => $text,
                'overall_status' => 'extracted',
                'processed_at' => now(),
            ]
        );
    }

    $application->unsetRelation('documents');
    $application->load(['documents.ocrResult.fields', 'documents.verification']);
}
