<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed();
});

function fakeOcrText(string $text): void
{
    Http::fake([
        'https://api.ocr.space/*' => Http::response([
            'OCRExitCode' => 1,
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [['ParsedText' => $text]],
        ], 200),
    ]);
}

test('uploading a document runs ocr and compares fields without verifying', function () {
    $application = Application::query()
        ->has('documents')
        ->has('applicant.user')
        ->with(['documents', 'applicant.profile', 'applicant.user'])
        ->firstOrFail();

    $application->update(['status' => ApplicationStatus::Accepted, 'assigned_staff_id' => null]);
    $document = $application->documents->firstWhere('program_requirement_id')
        ?? $application->documents->firstOrFail();
    $applicant = $application->applicant;
    $applicant->user->update(['is_active' => true]);

    fakeOcrText(
        "Certificate of Enrollment\nStudent Name: {$applicant->full_name}\nSchool: ".($applicant->profile?->school_name ?: 'Nabua National High School')."\nCurrently Enrolled"
    );

    $this->actingAs($applicant->user)
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $document->program_requirement_id,
            'file' => UploadedFile::fake()->image('enrollment.jpg'),
        ])
        ->assertRedirect();

    $uploaded = $application->fresh()->documents()
        ->where('program_requirement_id', $document->program_requirement_id)
        ->where(function ($query) {
            $query->whereNull('side')->orWhere('side', 'front');
        })
        ->firstOrFail();

    expect($uploaded->verification?->status)->toBe(DocumentVerificationStatus::Pending)
        ->and($uploaded->ocrResult)->not->toBeNull()
        ->and($uploaded->ocrResult->status)->toBe('processed')
        ->and($uploaded->ocrResult->fields->isNotEmpty())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.ocr.space'));
});

test('ocr type mismatch notifies the applicant but does not reject the file', function () {
    $application = Application::query()
        ->has('documents')
        ->has('applicant.user')
        ->with(['documents', 'applicant.user'])
        ->firstOrFail();

    $application->update(['status' => ApplicationStatus::Accepted]);
    $document = $application->documents
        ->first(fn ($row) => str_contains(strtolower($row->requirement_name), 'enrollment'))
        ?? $application->documents->firstWhere('program_requirement_id');

    expect($document)->not->toBeNull();
    $application->applicant->user->update(['is_active' => true]);

    fakeOcrText('Barangay Clearance issued by the Punong Barangay for residency purposes.');

    $this->actingAs($application->applicant->user)
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $document->program_requirement_id,
            'file' => UploadedFile::fake()->image('clearance.jpg'),
        ])
        ->assertRedirect();

    $uploaded = $application->fresh()->documents()
        ->where('program_requirement_id', $document->program_requirement_id)
        ->where(function ($query) {
            $query->whereNull('side')->orWhere('side', 'front');
        })
        ->firstOrFail();

    expect($uploaded->verification?->status)->toBe(DocumentVerificationStatus::Pending)
        ->and($uploaded->ocrResult?->overall_status)->toBe('type_mismatch');

    expect(SystemNotification::query()
        ->where('user_id', $application->applicant->user_id)
        ->where('title', 'Uploaded document may be the wrong type')
        ->exists())->toBeTrue();
});

test('philippine national id upload extracts name without verifying', function () {
    $application = Application::query()
        ->has('documents')
        ->has('applicant.user')
        ->with(['documents', 'applicant.user'])
        ->firstOrFail();

    $application->update(['status' => ApplicationStatus::Accepted]);
    $document = $application->documents
        ->first(fn ($row) => str_contains(strtolower($row->requirement_name), 'valid id'))
        ?? $application->documents->firstWhere('program_requirement_id');

    expect($document)->not->toBeNull();
    $application->applicant->update(['full_name' => 'JOHN LLOYD Paculan BLANQUERA']);
    $application->applicant->user->update(['is_active' => true]);

    fakeOcrText(<<<'TEXT'
Philippine National ID
APELYIDO/LAST NAME
BLANQUERA
MGA PANGALAN/GIVEN NAMES
JOHN LLOYD
GITNANG APELYIDO/ MIDDLE NAME
PACULAN
PETSA NG KAPANGANAKAN/DATE OF BIRTH
April 01, 2000
TEXT);

    $this->actingAs($application->applicant->user)
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $document->program_requirement_id,
            'file' => UploadedFile::fake()->image('philsys.jpg'),
        ])
        ->assertRedirect();

    $uploaded = $application->fresh()->documents()
        ->where('program_requirement_id', $document->program_requirement_id)
        ->where(function ($query) {
            $query->whereNull('side')->orWhere('side', 'front');
        })
        ->with('ocrResult.fields')
        ->firstOrFail();

    $name = $uploaded->ocrResult?->fields->firstWhere('field_key', 'full_name');

    expect($uploaded->verification?->status)->toBe(DocumentVerificationStatus::Pending)
        ->and($name?->extracted_value)->toBe('JOHN LLOYD PACULAN BLANQUERA')
        ->and($name?->match_status)->toBe('matched');
});

test('staff can correct ocr fields without verifying the document', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()
        ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification])
        ->has('documents')
        ->with('documents')
        ->firstOrFail();
    $document = $application->documents->firstOrFail();
    Storage::disk('public')->put($document->file_path, 'fake-document');

    fakeOcrText('Student Name: Juan Sample');

    $this->actingAs($admin)
        ->post(route('admin.applications.documents.ocr', [$application, $document]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $field = $document->fresh()->ocrResult?->fields->first();
    expect($field)->not->toBeNull()
        ->and($document->fresh()->verification?->status)->not->toBe(DocumentVerificationStatus::Verified);

    $this->actingAs($admin)
        ->put(route('admin.applications.documents.ocr.update', [$application, $document]), [
            'fields' => [
                ['id' => $field->id, 'corrected_value' => $application->applicant?->full_name ?: 'Corrected Name'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($field->fresh()->corrected_value)->not->toBeNull()
        ->and($document->fresh()->verification?->status)->not->toBe(DocumentVerificationStatus::Verified);
});
