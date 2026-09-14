<?php

namespace App\Services;

use App\Enums\BeneficiaryType;
use App\Enums\DocumentVerificationStatus;
use App\Mail\ApplicationOtpMail;
use App\Mail\ApplicationReceivedMail;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\DocumentSubmission;
use App\Models\DocumentVerification;
use App\Models\Role;
use App\Models\User;
use App\Support\DocumentFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicApplicationService
{
    private ?string $issuedPassword = null;

    public function __construct(
        private ApplicationService $applications,
        private AuditService $audit,
    ) {}

    public function sendOtp(string $email, string $fullName): void
    {
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->otpKey($email), Hash::make($otp), now()->addMinutes(10));

        try {
            Mail::to($email)->send(new ApplicationOtpMail($fullName, $otp));
        } catch (\Throwable $exception) {
            report($exception);
            Cache::forget($this->otpKey($email));

            throw ValidationException::withMessages([
                'email' => 'The verification email could not be sent. Please try again or contact the office.',
            ]);
        }
    }

    public function submit(AssistanceProgram $program, array $data, ?UploadedFile $id = null): Application
    {
        $this->issuedPassword = null;
        $this->assertOtp($data['email'], $data['otp']);

        if (! $program->isCurrentlyOpen()) {
            throw ValidationException::withMessages([
                'program' => 'This program is not currently open for applications.',
            ]);
        }

        $type = $data['beneficiary_type'] === 'student'
            ? BeneficiaryType::Student
            : BeneficiaryType::NonStudent;

        if (! $program->acceptsBeneficiary($type)) {
            throw ValidationException::withMessages([
                'beneficiary_type' => 'This program does not cover the selected beneficiary type.',
            ]);
        }

        $application = DB::transaction(function () use ($program, $data, $id, $type) {
            $applicant = $this->resolveApplicant($data, $type);
            $application = $this->applications->start($applicant, $program);
            $application->load('program.formFields');

            if ($applicant->user) {
                $this->applications->confirmEligibility($application, $applicant->user);
                $this->applications->saveAnswers($application, [
                    'school_name' => $data['school_name'] ?? null,
                    'course_or_program' => $data['course_or_program'] ?? null,
                    'year_level' => $data['year_level'] ?? null,
                ], $applicant->user);
            }

            $application->update([
                'current_step' => 3,
            ]);

            if ($id) {
                $this->storeValidId($application, $id);
            }

            return $application->fresh(['applicant', 'program']);
        });

        Cache::forget($this->otpKey($data['email']));

        try {
            // Send the confirmation without any credentials — those are issued
            // only when an admin approves the applicant.
            Mail::to($data['email'])->send(new ApplicationReceivedMail($application, null));
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->audit->log(
            'created',
            'Public application '.$application->application_no.' filed for '.$program->name,
            $application,
            $application,
            $application->applicant?->user,
        );

        return $application;
    }

    private function resolveApplicant(array $data, BeneficiaryType $type): Applicant
    {
        $existingUser = User::query()->where('email', $data['email'])->first();

        if ($existingUser && ! $existingUser->isApplicant()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already used by a staff account.',
            ]);
        }

        if ($existingUser?->applicant) {
            $applicant = $existingUser->applicant;
            $applicant->update([
                'full_name' => $data['full_name'],
                'date_of_birth' => $data['date_of_birth'],
                'sex' => $data['sex'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
            ]);
            $existingUser->update([
                'name' => $data['full_name'],
                'is_active' => true,
            ]);
            $this->syncProfileAndAddress($applicant, $data, $type);

            return $applicant->fresh(['profile', 'primaryAddress', 'user']);
        }

        $roleId = Role::query()->where('slug', 'applicant')->value('id');

        // Create the account in a locked state. The password is a random placeholder
        // that the applicant never sees; a real password is generated and emailed
        // only when an administrator approves the application.
        $user = User::query()->create([
            'role_id' => $roleId,
            'name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Str::password(48, symbols: false),
            'is_active' => false,
            'pending_account' => true,
        ]);

        $applicant = Applicant::query()->create([
            'user_id' => $user->id,
            'applicant_no' => $this->applications->generateNumber(gov('applicant_prefix', 'BEN'), 'applicants', 'applicant_no'),
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'sex' => $data['sex'],
            'contact_number' => $data['contact_number'],
            'email' => $data['email'],
        ]);

        $this->syncProfileAndAddress($applicant, $data, $type);

        return $applicant->fresh(['profile', 'primaryAddress', 'user']);
    }

    private function syncProfileAndAddress(Applicant $applicant, array $data, BeneficiaryType $type): void
    {
        $applicant->profile()->updateOrCreate(
            ['applicant_id' => $applicant->id],
            [
                'beneficiary_type' => $type,
                'school_name' => $data['school_name'] ?: null,
                'course_or_program' => $data['course_or_program'] ?: null,
                'year_level' => $data['year_level'] ?: null,
                'mother_name' => $data['mother_name'],
                'mother_occupation' => $data['mother_occupation'],
                'father_name' => $data['father_name'],
                'father_occupation' => $data['father_occupation'],
                'is_pwd' => (bool) $data['is_pwd'],
                'pwd_type' => $data['is_pwd'] ? ($data['pwd_type'] ?? null) : null,
                'pwd_type_detail' => $data['is_pwd'] ? ($data['pwd_type_detail'] ?? null) : null,
            ]
        );

        $applicant->addresses()->updateOrCreate(
            ['applicant_id' => $applicant->id, 'is_primary' => true],
            [
                'street' => $data['street'],
                'barangay' => $data['barangay'],
                'municipality' => $data['municipality'],
                'province' => $data['province'],
                'is_primary' => true,
            ]
        );
    }

    private function storeValidId(Application $application, UploadedFile $file): void
    {
        $path = DocumentFiles::store($file, $application->id);

        $document = DocumentSubmission::query()->create([
            'application_id' => $application->id,
            'program_requirement_id' => null,
            'requirement_name' => 'Valid ID',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName() ?: 'valid-id.'.$file->getClientOriginalExtension(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        DocumentVerification::query()->create([
            'document_submission_id' => $document->id,
            'status' => DocumentVerificationStatus::Pending,
        ]);
    }

    private function assertOtp(string $email, string $otp): void
    {
        $hashed = Cache::get($this->otpKey($email));

        if (! $hashed || ! Hash::check($otp, $hashed)) {
            throw ValidationException::withMessages([
                'otp' => 'The verification code is invalid or has expired.',
            ]);
        }
    }

    private function otpKey(string $email): string
    {
        return 'apply-otp:'.strtolower(trim($email));
    }
}
