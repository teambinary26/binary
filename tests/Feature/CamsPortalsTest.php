<?php

use App\Enums\BeneficiaryType;
use App\Enums\WorkflowStep;
use App\Models\Applicant;
use App\Models\ApplicantAddress;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\AssistanceProgram;
use App\Models\AssistanceRelease;
use App\Models\DocumentSubmission;
use App\Models\ProgramCategory;
use App\Models\ReleaseSchedule;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkflowStaff;
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
            ->has('programs.data')
            ->has('programs.links')
            ->has('announcements')
            ->has('quick')
        );
});

test('public pages share office identity from system settings', function () {
    $identity = [
        'agency' => 'Municipal Social Welfare and Development Office',
        'lgu' => 'Municipality of San Rafael',
        'province' => 'Province of Nueva Ecija',
        'address' => 'Municipal Hall Compound, Poblacion, San Rafael, Nueva Ecija',
        'phone' => '(044) 940-2100',
        'email' => 'mswdo@sanrafael.gov.ph',
        'office_hours' => 'Monday to Friday, 8:00 AM - 5:00 PM',
    ];

    foreach ($identity as $key => $value) {
        SystemSetting::setValue($key, $value);
    }

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Home')
            ->where('gov.agency', $identity['agency'])
            ->where('gov.lgu', $identity['lgu'])
            ->where('gov.province', $identity['province'])
            ->where('gov.address', $identity['address'])
            ->where('gov.phone', $identity['phone'])
            ->where('gov.email', $identity['email'])
            ->where('gov.office_hours', $identity['office_hours'])
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
            ->has('recent.data')
            ->has('recent.links')
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
            ->has('applications.data')
            ->has('applications.links')
            ->has('counts')
        );
});

test('staff cannot open user management', function () {
    $user = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

test('admin users page lists accounts in separate role tables', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('administrators.data')
            ->has('sk.data')
            ->has('staff.data')
            ->has('applicants.data')
            ->has('roles')
            ->where('administrators.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['role'] === 'Administrator'))
            ->where('sk.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['role'] === 'Sangguniang Kabataan'))
            ->where('staff.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['role'] === 'Staff'))
            ->where('applicants.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['role'] === 'Applicant'))
            ->where('roles.0.slug', 'administrator')
            ->where('roles.1.slug', 'sk')
            ->where('roles.2.slug', 'staff')
        );
});

test('create user role list includes sk even if the role was missing', function () {
    $staffId = Role::query()->where('slug', 'staff')->value('id');
    $sk = Role::query()->where('slug', 'sk')->first();

    if ($sk) {
        User::query()->where('role_id', $sk->id)->update(['role_id' => $staffId]);
        $sk->permissions()->detach();
        $sk->delete();
    }

    expect(Role::query()->where('slug', 'sk')->exists())->toBeFalse();

    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles.1.slug', 'sk')
            ->where('roles.1.name', 'Sangguniang Kabataan')
        );

    expect(Role::query()->where('slug', 'sk')->exists())->toBeTrue();
});

test('admin can create a staff user', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $role = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => 'New Staff Member',
            'email' => 'new.staff@nabua.gov.ph',
            'office' => 'MSWDO',
            'role_id' => $role->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $created = User::query()->where('email', 'new.staff@nabua.gov.ph')->first();

    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('New Staff Member')
        ->and($created->role_id)->toBe($role->id);
});

test('admin can create another administrator', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => 'New Administrator',
            'email' => 'admin2@nabua.gov.ph',
            'office' => 'MSWDO',
            'role_id' => $role->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'Administrator account created.');

    $created = User::query()->where('email', 'admin2@nabua.gov.ph')->first();

    expect($created)->not->toBeNull()
        ->and($created->isAdmin())->toBeTrue();
});

test('admin can create an sk account assigned to verification', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $role = Role::query()->where('slug', 'sk')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => 'SK Chairperson',
            'email' => 'sk.malawag@nabua.gov.ph',
            'office' => 'SK Malawag',
            'role_id' => $role->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'SK account created and assigned to verification.');

    $created = User::query()->where('email', 'sk.malawag@nabua.gov.ph')->first();

    expect($created)->not->toBeNull()
        ->and($created->isSk())->toBeTrue()
        ->and(WorkflowStaff::isAssignedToStep($created->id, WorkflowStep::Verification))->toBeTrue();
});

test('seeded sk accounts are assigned to verification and can open the verification queue', function () {
    $sk = User::query()->where('email', 'sk@nabua.gov.ph')->firstOrFail();

    expect($sk->isSk())->toBeTrue()
        ->and($sk->canAccessAdmin())->toBeTrue()
        ->and(WorkflowStaff::isAssignedToStep($sk->id, WorkflowStep::Verification))->toBeTrue();

    $this->actingAs($sk)
        ->get(route('admin.verification.index'))
        ->assertOk();

    $this->actingAs($sk)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($sk)->get(route('admin.programs.index'))->assertForbidden();
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Programs/Index')
            ->has('programs.data.0.can_delete')
        );

    $this->actingAs($user)->get('/admin/reports/applications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Applications'));
});

test('admin can delete a program that has no applications', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $category = ProgramCategory::query()->firstOrFail();

    $program = AssistanceProgram::query()->create([
        'program_category_id' => $category->id,
        'name' => 'Temporary Test Program',
        'code' => 'TMP-DEL',
        'slug' => 'temporary-test-program',
        'description' => 'Created only to verify deletion.',
        'eligibility' => 'Test eligibility.',
        'beneficiary_type' => BeneficiaryType::Both,
        'amount_type' => 'fixed',
        'amount' => 1000,
        'is_open' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.programs.index'))
        ->delete(route('admin.programs.destroy', $program))
        ->assertRedirect(route('admin.programs.index'))
        ->assertSessionHas('success');

    expect(AssistanceProgram::query()->whereKey($program->id)->exists())->toBeFalse();
});

test('admin cannot delete a program that has applications', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $program = AssistanceProgram::query()->has('applications')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.programs.index'))
        ->delete(route('admin.programs.destroy', $program))
        ->assertRedirect(route('admin.programs.index'))
        ->assertSessionHas('error');

    expect(AssistanceProgram::query()->whereKey($program->id)->exists())->toBeTrue();
});

test('admin can delete selected applications in bulk', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $ids = Application::query()->orderBy('id')->limit(2)->pluck('id');

    expect($ids)->toHaveCount(2);

    $this->actingAs($admin)
        ->from(route('admin.applications.index'))
        ->post(route('admin.applications.bulk-destroy'), ['ids' => $ids->all()])
        ->assertRedirect(route('admin.applications.index'))
        ->assertSessionHas('success');

    expect(Application::query()->whereIn('id', $ids)->exists())->toBeFalse();
});

test('staff cannot bulk delete applications', function () {
    $staff = User::query()->where('email', 'staff@nabua.gov.ph')->firstOrFail();
    $id = Application::query()->value('id');

    $this->actingAs($staff)
        ->post(route('admin.applications.bulk-destroy'), ['ids' => [$id]])
        ->assertForbidden();

    expect(Application::query()->whereKey($id)->exists())->toBeTrue();
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

test('listing pages expose paginated records', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $applicant = User::query()->where('email', 'juan.delacruz@email.com')->firstOrFail();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data')
            ->has('programs.total')
            ->has('programs.links')
        );

    $this->get('/programs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data')
            ->has('programs.total')
        );

    $this->get('/requirements')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data')
            ->has('programs.total')
        );

    $this->actingAs($admin)->get('/admin/categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories.data')
            ->has('categories.links')
        );

    $this->actingAs($admin)->get('/admin/requirements')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data')
            ->has('programOptions')
        );

    $this->actingAs($admin)->get('/admin/reports/financial')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('byProgram.data')
            ->has('byProgram.total')
        );

    $this->actingAs($admin)->get('/admin/reports/beneficiaries')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('byBarangay.data')
            ->has('byType.data')
            ->has('bySex.data')
        );

    $this->actingAs($admin)->get('/admin/releases')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('approved.data')
            ->has('approved.links')
            ->has('schedules.data')
        );

    $this->actingAs($applicant)->get('/applicant/programs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data')
            ->has('programs.total')
        );

    $firstPage = $this->actingAs($admin)->get('/admin/applications')->assertOk();
    $firstPage->assertInertia(fn (Assert $page) => $page
        ->has('applications.data')
        ->has('applications.total')
        ->has('applications.current_page')
    );

    $total = Application::query()->count();
    if ($total > 15) {
        $this->actingAs($admin)
            ->get('/admin/applications?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('applications.current_page', 2)
                ->has('applications.data')
            );
    }
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
