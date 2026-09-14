<?php

use App\Models\Applicant;
use App\Models\ApplicantAddress;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\AssistanceRelease;
use App\Models\DocumentSubmission;
use App\Models\ReleaseSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed();
});

test('public home page is reachable', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->has('programs')
            ->has('announcements')
            ->has('quick')
        );
});

test('staff dashboard loads for administrator', function () {
    $user = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
            ->has('recent')
        );
});

test('applicant dashboard loads for registered beneficiary', function () {
    $user = User::query()->where('email', 'juan.delacruz@email.com')->firstOrFail();

    $this->actingAs($user)
        ->get('/applicant/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Applicant/Dashboard')
            ->has('applicant')
            ->has('applications')
            ->has('counts')
        );
});

test('staff cannot open user management', function () {
    $user = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

test('admin can open application processing and program maintenance', function () {
    $user = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()->firstOrFail();

    $this->actingAs($user)->get('/admin/applications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Applications/Index'));

    $this->actingAs($user)->get('/admin/applications/'.$application->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Applications/Show')
            ->where('application.application_no', $application->application_no)
            ->has('application.answers')
        );

    $this->actingAs($user)->get('/admin/programs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Programs/Index'));

    $this->actingAs($user)->get('/admin/reports/applications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Applications'));
});

test('staff can record a document verification', function () {
    $user = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $document = DocumentSubmission::query()->firstOrFail();
    $application = $document->application;

    $this->actingAs($user)
        ->from(route('admin.applications.show', $application))
        ->post(route('admin.applications.documents.verify', [$application, $document]), [
            'action' => 'verify',
        ])
        ->assertRedirect(route('admin.applications.show', $application))
        ->assertSessionHas('success');
});

test('admin can download an application document', function () {
    $user = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $document = DocumentSubmission::query()->firstOrFail();

    $this->actingAs($user)
        ->get(route('admin.applications.documents.download', [$document->application_id, $document->id]))
        ->assertOk()
        ->assertDownload($document->original_name);
});

test('admin can preview an application image document', function () {
    $user = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()->firstOrFail();
    $path = UploadedFile::fake()->image('valid-id.jpg')->storeAs('documents/'.$application->id, 'valid-id.jpg', 'public');

    $document = DocumentSubmission::query()->create([
        'application_id' => $application->id,
        'requirement_name' => 'Valid ID',
        'file_path' => $path,
        'original_name' => 'valid-id.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
        'uploaded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.applications.documents.preview', [$application, $document]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    $this->actingAs($user)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('application.documents')
            ->where('application.documents', fn ($documents) => collect($documents)->contains(
                fn ($row) => $row['id'] === $document->id && $row['is_image'] === true
            ))
        );
});

test('guest visiting the staff dashboard is sent to sign in', function () {
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

test('guest sign-in page is reachable', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('applicant visiting the staff dashboard is sent to the applicant portal', function () {
    $user = User::query()->where('email', 'juan.delacruz@email.com')->firstOrFail();

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertRedirect('/applicant/dashboard');
});

test('authenticated staff visiting login are sent to the staff dashboard', function () {
    $user = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/admin/dashboard');
});

test('inactive staff cannot open the staff dashboard', function () {
    $user = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $user->update(['is_active' => false]);

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertRedirect('/login');
});

test('admin can delete a beneficiary without applications', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $applicant = Applicant::query()
        ->whereDoesntHave('applications')
        ->first();

    if (! $applicant) {
        $this->markTestSkipped('No applicant without applications in demo data.');
    }

    $this->actingAs($admin)
        ->from(route('admin.applicants.index'))
        ->delete(route('admin.applicants.destroy', $applicant))
        ->assertRedirect(route('admin.applicants.index'))
        ->assertSessionHas('success');

    expect(Applicant::query()->whereKey($applicant->id)->exists())->toBeFalse();
});

test('admin cannot delete a beneficiary with applications', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $applicant = Applicant::query()
        ->whereHas('applications')
        ->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.applicants.index'))
        ->delete(route('admin.applicants.destroy', $applicant))
        ->assertRedirect(route('admin.applicants.index'))
        ->assertSessionHas('error');

    expect(Applicant::query()->whereKey($applicant->id)->exists())->toBeTrue();
});

test('admin can delete an applicant user and all connected records', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $applicantUser = User::query()->where('email', 'sofia.ramos@email.com')->firstOrFail();
    $applicant = $applicantUser->applicant()->firstOrFail();
    $applicantId = $applicant->id;
    $applicationIds = $applicant->applications()->pluck('id');

    expect($applicationIds)->not->toBeEmpty();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $applicantUser))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect(User::query()->whereKey($applicantUser->id)->exists())->toBeFalse();
    expect(Applicant::query()->whereKey($applicantId)->exists())->toBeFalse();
    expect(ApplicantProfile::query()->where('applicant_id', $applicantId)->exists())->toBeFalse();
    expect(ApplicantAddress::query()->where('applicant_id', $applicantId)->exists())->toBeFalse();
    expect(Application::query()->whereIn('id', $applicationIds)->exists())->toBeFalse();
    expect(ApplicationAnswer::query()->whereIn('application_id', $applicationIds)->exists())->toBeFalse();
    expect(DocumentSubmission::query()->whereIn('application_id', $applicationIds)->exists())->toBeFalse();
    expect(ReleaseSchedule::query()->whereIn('application_id', $applicationIds)->exists())->toBeFalse();
    expect(AssistanceRelease::query()->whereIn('application_id', $applicationIds)->exists())->toBeFalse();
});

test('admin cannot delete their own account', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('admin can delete a staff user with no operational records', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $roleId = Role::query()->where('slug', 'staff')->value('id');
    $staff = User::factory()->create([
        'role_id' => $roleId,
        'name' => 'Temporary Staff',
        'office' => 'Records',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $staff))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    expect(User::query()->whereKey($staff->id)->exists())->toBeFalse();
});

test('admin cannot delete a staff user linked to operational records', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $staff))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    expect(User::query()->whereKey($staff->id)->exists())->toBeTrue();
});

test('staff cannot delete system users', function () {
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $applicantUser = User::query()->where('email', 'juan.delacruz@email.com')->firstOrFail();

    $this->actingAs($staff)
        ->delete(route('admin.users.destroy', $applicantUser))
        ->assertForbidden();
});

test('program directory and how-to-apply pages render', function () {
    $this->get('/programs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Programs/Index'));

    $this->get('/how-to-apply')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/HowToApply'));

    $this->get('/application-status')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Status'));
});

test('public status inquiry finds an application by number only', function () {
    $application = Application::query()->with('applicant')->firstOrFail();
    $appliedAt = $application->submitted_at ?? $application->created_at;

    $this->get('/application-status?application_no='.$application->application_no)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Status')
            ->where('searched', true)
            ->where('application.application_no', $application->application_no)
            ->where('application.applicant.full_name', $application->applicant->full_name)
            ->where('application.submitted_at_full', gov_datetime($appliedAt))
            ->missing('application.timeline')
        );

    $this->get('/application-status?application_no=CAMS-0000-999999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Status')
            ->where('searched', true)
            ->where('application', null)
        );
});
