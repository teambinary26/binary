<?php

use App\Enums\ApplicationStatus;
use App\Mail\ApplicationOtpMail;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\AssistanceProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed();
    Mail::fake();
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
            'error-codes' => [],
        ], 200),
    ]);
    config([
        'services.turnstile.secret_key' => 'test-secret',
        'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ]);
});

function dynamicFieldProgram(): AssistanceProgram
{
    return AssistanceProgram::query()
        ->where('code', 'EDU-001')
        ->firstOrFail();
}

function extraRequiredField(AssistanceProgram $program): string
{
    $program->loadMissing('formFields');

    $field = $program->formFields->first(
        fn ($item) => $item->is_required
            && in_array($item->type, ['text', 'textarea'], true)
            && ! in_array($item->name, ['school_name', 'course_or_program', 'year_level'], true)
    );

    expect($field)->not->toBeNull();

    return $field->name;
}

function dynamicFieldAnswers(AssistanceProgram $program, array $overrides = []): array
{
    $program->loadMissing('formFields');
    $answers = [];

    foreach ($program->formFields as $field) {
        $answers[$field->name] = match (true) {
            $field->name === 'school_name' => 'Nabua National High School',
            $field->name === 'course_or_program' => 'General Academic Strand',
            $field->name === 'year_level' => 'Grade 12',
            $field->type === 'number' => '1500',
            $field->type === 'date' => '2026-01-15',
            $field->type === 'select' && filled($field->options) => $field->options[0],
            default => 'Sample '.$field->label,
        };
    }

    return array_merge($answers, $overrides);
}

function publicApplyPayload(string $otp, AssistanceProgram $program, array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ana Public Applicant',
        'date_of_birth' => '2005-03-15',
        'sex' => 'female',
        'street' => '12 Mabini Street',
        'barangay' => 'Poblacion',
        'municipality' => 'Nabua',
        'province' => 'Camarines Sur',
        'contact_number' => '09171234567',
        'email' => 'ana.fields@example.com',
        'mother_name' => 'Rosa Applicant',
        'mother_occupation' => 'Vendor',
        'father_name' => 'Pedro Applicant',
        'father_occupation' => 'Farmer',
        'is_pwd' => 0,
        'school_name' => 'Nabua National High School',
        'course_or_program' => 'General Academic Strand',
        'year_level' => 'Grade 12',
        'beneficiary_type' => 'student',
        'otp' => $otp,
        'eligibility_confirmed' => 1,
        'turnstile_token' => 'test-token',
        'answers' => dynamicFieldAnswers($program),
    ], $overrides);
}

function programUpdatePayload(AssistanceProgram $program, array $extraFields = []): array
{
    $program->loadMissing(['formFields', 'requirements', 'eligibilityRules']);

    $fields = $program->formFields->map(fn ($field) => [
        'label' => $field->label,
        'name' => $field->name,
        'type' => $field->type,
        'options' => is_array($field->options) ? implode(', ', $field->options) : '',
        'help_text' => $field->help_text,
        'is_required' => $field->is_required,
    ])->all();

    foreach ($extraFields as $field) {
        $fields[] = $field;
    }

    return [
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
        'fields' => $fields,
        'requirements' => $program->requirements->map(fn ($requirement) => [
            'name' => $requirement->name,
            'description' => $requirement->description,
            'is_required' => $requirement->is_required,
            'ocr_fields' => $requirement->ocr_fields ?: [],
        ])->all(),
        'rules' => $program->eligibilityRules->map(fn ($rule) => [
            'label' => $rule->label,
            'check_mode' => $rule->check_mode ?: 'ocr',
            'field' => $rule->field,
            'operator' => $rule->operator,
            'value' => $rule->value,
        ])->all(),
    ];
}

test('program details page lists admin-defined application questions', function () {
    $program = dynamicFieldProgram()->load('formFields');

    $this->get(route('site.programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Programs/Show')
            ->has('program.form_fields', $program->formFields->count())
            ->where('program.form_fields.0.label', $program->formFields->first()->label)
        );
});

test('admin can add a dynamic field and it appears on the public apply form', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $program = dynamicFieldProgram();

    $this->actingAs($admin)
        ->from(route('admin.programs.edit', $program))
        ->put(route('admin.programs.update', $program), programUpdatePayload($program, [[
            'label' => 'Proposed Livelihood',
            'name' => '',
            'type' => 'text',
            'options' => '',
            'help_text' => 'Describe the activity.',
            'is_required' => true,
        ]]))
        ->assertRedirect();

    $program->refresh()->load('formFields');

    expect($program->formFields->pluck('name'))->toContain('proposed_livelihood')
        ->and($program->formFields->firstWhere('name', 'proposed_livelihood')->label)->toBe('Proposed Livelihood');

    $this->get(route('site.apply.create', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Programs/Apply')
            ->where('program.form_fields', fn ($fields) => collect($fields)->contains('name', 'proposed_livelihood'))
        );
});

test('public apply stores program-specific answers and shows them to staff', function () {
    $program = dynamicFieldProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.fields@example.com',
    ])->assertOk();

    $otp = null;
    Mail::assertSent(ApplicationOtpMail::class, function (ApplicationOtpMail $mail) use (&$otp) {
        $otp = $mail->otp;

        return $mail->hasTo('ana.fields@example.com');
    });

    $extraField = extraRequiredField($program);
    $answers = dynamicFieldAnswers($program, [
        $extraField => 'Support school fees this semester',
    ]);

    $this->post((string) route('site.apply.store', $program), publicApplyPayload($otp, $program, [
        'email' => 'ana.fields@example.com',
        'answers' => $answers,
    ]))->assertRedirect(route('site.apply.success'));

    $application = Application::query()
        ->whereHas('applicant', fn ($query) => $query->where('email', 'ana.fields@example.com'))
        ->with('answers')
        ->firstOrFail();

    expect($application->answers->firstWhere('field_name', $extraField)?->value)->toBe('Support school fees this semester')
        ->and($application->answers->firstWhere('field_name', 'school_name')?->value)->toBe('Nabua National High School');

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Applications/Show')
            ->where('application.answers', function ($rows) use ($extraField) {
                return collect($rows)->contains(fn ($row) => $row['field_name'] === $extraField
                    && $row['value'] === 'Support school fees this semester');
            })
        );
});

test('public apply requires program-specific fields added by the admin', function () {
    $program = dynamicFieldProgram();

    $this->postJson(route('site.apply.otp', $program), [
        'turnstile_token' => 'test-token',
        'full_name' => 'Ana Public Applicant',
        'email' => 'ana.missing-field@example.com',
    ])->assertOk();

    $otp = null;
    Mail::assertSent(ApplicationOtpMail::class, function (ApplicationOtpMail $mail) use (&$otp) {
        $otp = $mail->otp;

        return $mail->hasTo('ana.missing-field@example.com');
    });

    $answers = dynamicFieldAnswers($program);
    $missing = extraRequiredField($program);
    unset($answers[$missing]);

    $this->from((string) route('site.apply.create', $program))
        ->post((string) route('site.apply.store', $program), publicApplyPayload($otp, $program, [
            'email' => 'ana.missing-field@example.com',
            'answers' => $answers,
        ]))
        ->assertRedirect(route('site.apply.create', $program))
        ->assertSessionHasErrors('answers.'.$missing);
});

test('applicant portal form shows and saves dynamic program fields', function () {
    $user = User::query()->where('email', 'juan.delacruz@email.com')->firstOrFail();
    $program = dynamicFieldProgram()->load('formFields');

    $application = Application::query()->create([
        'application_no' => 'CAMS-FORM-TEST-01',
        'applicant_id' => $user->applicant->id,
        'assistance_program_id' => $program->id,
        'status' => ApplicationStatus::Draft,
        'current_step' => 2,
    ]);

    expect($application->continuePath())->toBe(route('applicant.apply.form', $application));

    $this->actingAs($user)
        ->get(route('applicant.apply.form', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Apply/Form')
            ->has('fields', $program->formFields->count())
            ->where('fields.0.name', $program->formFields->first()->name)
        );

    $extraField = extraRequiredField($program);

    $this->actingAs($user)
        ->from(route('applicant.apply.form', $application))
        ->post(route('applicant.apply.form.store', $application), dynamicFieldAnswers($program, [
            $extraField => 'Buy school supplies for the term',
        ]))
        ->assertRedirect(route('applicant.apply.documents', $application));

    $saved = ApplicationAnswer::query()
        ->where('application_id', $application->id)
        ->pluck('value', 'field_name');

    expect($saved[$extraField])->toBe('Buy school supplies for the term')
        ->and($saved['school_name'])->toBe('Nabua National High School');

    $this->actingAs($user)
        ->get(route('applicant.apply.review', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('application.answers', function ($rows) use ($extraField) {
                return collect($rows)->contains(fn ($row) => $row['field_name'] === $extraField
                    && $row['value'] === 'Buy school supplies for the term');
            })
        );
});
