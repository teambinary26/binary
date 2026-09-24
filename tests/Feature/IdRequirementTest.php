<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Support\IdRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Http::fake([
        'https://api.ocr.space/*' => Http::response([
            'OCRExitCode' => 1,
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [['ParsedText' => 'Valid ID back']],
        ], 200),
    ]);
    $this->seed();
});

test('an id requirement stays incomplete until the back side is uploaded', function () {
    $application = Application::query()
        ->whereHas('program.requirements', fn ($query) => $query->where('name', 'like', '%ID%'))
        ->has('applicant.user')
        ->with(['documents', 'program.requirements', 'applicant.user'])
        ->firstOrFail();

    $application->update(['status' => ApplicationStatus::Accepted, 'assigned_staff_id' => null]);
    $user = $application->applicant->user;
    $user->update(['is_active' => true]);

    $requirement = $application->program->requirements->first(
        fn ($item) => IdRequirements::requiresBack($item->name)
    );
    $application->documents()
        ->where('program_requirement_id', $requirement->id)
        ->where('side', 'back')
        ->delete();
    $application->unsetRelation('documents');
    $application->load('documents');

    $front = $application->documentForRequirement($requirement->id, 'front');

    expect($front)->not->toBeNull()
        ->and($application->missingRequiredRequirements()->contains('id', $requirement->id))->toBeTrue()
        ->and($application->requiredDocumentsVerified())->toBeFalse();

    $this->actingAs($user)
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $requirement->id,
            'side' => 'back',
            'file' => UploadedFile::fake()->image('id-back.jpg'),
        ])
        ->assertRedirect();

    $application->unsetRelation('documents');
    $application->load(['documents.verification']);

    $back = $application->documentForRequirement($requirement->id, 'back');

    expect($application->documentForRequirement($requirement->id, 'front')?->id)->toBe($front->id)
        ->and($back)->not->toBeNull()
        ->and($back->requirement_name)->toBe($requirement->name.' (back)')
        ->and($application->missingRequiredRequirements()->contains('id', $requirement->id))->toBeFalse()
        ->and($back->verification?->status)->toBe(DocumentVerificationStatus::Pending);
});

test('a requirement that is not an id rejects a back side upload', function () {
    $application = Application::query()
        ->has('applicant.user')
        ->with(['program.requirements', 'applicant.user'])
        ->get()
        ->first(fn (Application $application) => $application->program->requirements->contains(
            fn ($requirement) => ! IdRequirements::requiresBack($requirement->name)
        ));

    $application->update(['status' => ApplicationStatus::Accepted, 'assigned_staff_id' => null]);
    $user = $application->applicant->user;
    $user->update(['is_active' => true]);
    $requirement = $application->program->requirements->first(
        fn ($item) => ! IdRequirements::requiresBack($item->name)
    );

    $this->actingAs($user)
        ->from(route('applicant.apply.documents', $application))
        ->post(route('applicant.apply.documents.store', $application), [
            'requirement_id' => $requirement->id,
            'side' => 'back',
            'file' => UploadedFile::fake()->image('not-an-id.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('document');
});
