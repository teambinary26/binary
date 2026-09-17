<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryType;
use App\Enums\DocumentVerificationStatus;
use App\Enums\WorkflowStep;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkflowStaff;
use App\Support\DocumentFiles;
use App\Support\OcrFields;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Password123!');
        $roles = Role::query()->pluck('id', 'slug');

        $municipal = [
            ['administrator', 'Maria Cristina Reyes', 'admin@nabua.gov.ph', 'MSWDO-0001', 'MSWDO'],
            ['staff', 'Jose Antonio Villanueva', 'staff@nabua.gov.ph', 'MSWDO-0002', 'MSWDO'],
            ['sk', 'Rafael Mendoza', 'sk@nabua.gov.ph', 'SK-0001', 'Sangguniang Kabataan Federation'],
            ['sk', 'Kristine Mae Lopez', 'sk.poblacion@nabua.gov.ph', 'SK-0002', 'SK Poblacion'],
            ['sk', 'John Paulo Rivera', 'sk.sanjose@nabua.gov.ph', 'SK-0003', 'SK San Jose'],
        ];

        $users = [];
        $skUsers = [];
        $keepEmails = [];
        foreach ($municipal as [$role, $name, $email, $employee, $office]) {
            $keepEmails[] = $email;
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'role_id' => $roles[$role],
                    'name' => $name,
                    'employee_no' => $employee,
                    'office' => $office,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $users[$role] = $user;
            if ($role === 'sk') {
                $skUsers[] = $user;
            }
        }

        User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('slug', Role::MUNICIPAL_SLUGS))
            ->whereNotIn('email', $keepEmails)
            ->update(['is_active' => false]);

        WorkflowStaff::query()->firstOrCreate([
            'workflow_step' => WorkflowStep::Verification->value,
            'user_id' => $users['staff']->id,
        ]);
        foreach ($skUsers as $skUser) {
            WorkflowStaff::query()->firstOrCreate([
                'workflow_step' => WorkflowStep::Verification->value,
                'user_id' => $skUser->id,
            ]);
        }
        WorkflowStaff::query()->firstOrCreate([
            'workflow_step' => WorkflowStep::Evaluation->value,
            'user_id' => $users['staff']->id,
        ]);
        WorkflowStaff::query()->firstOrCreate([
            'workflow_step' => WorkflowStep::Approval->value,
            'user_id' => $users['administrator']->id,
        ]);

        $programs = AssistanceProgram::query()->with(['requirements', 'formFields', 'category'])->get();

        $people = [
            ['Juan Dela Cruz', 'male', 'student', 'Poblacion', 'juan.delacruz@email.com'],
            ['Maria Santos', 'female', 'student', 'San Jose', 'maria.santos@email.com'],
            ['Pedro Ramirez', 'male', 'non_student', 'Maligaya', 'pedro.ramirez@email.com'],
            ['Ana Villanueva', 'female', 'non_student', 'Santa Cruz', 'ana.villanueva@email.com'],
            ['Carlo Mendoza', 'male', 'student', 'Bagong Silang', 'carlo.mendoza@email.com'],
            ['Liza Navarro', 'female', 'non_student', 'San Antonio', 'liza.navarro@email.com'],
            ['Miguel Torres', 'male', 'student', 'Santo Niño', 'miguel.torres@email.com'],
            ['Rosa Gutierrez', 'female', 'non_student', 'Gabihan', 'rosa.gutierrez@email.com'],
            ['Andres Bautista', 'male', 'non_student', 'Ligaya', 'andres.bautista@email.com'],
            ['Sofia Ramos', 'female', 'student', 'Villa Ofelia', 'sofia.ramos@email.com'],
            ['Ramon Ignacio', 'male', 'non_student', 'Poblacion', 'ramon.ignacio@email.com'],
            ['Teresa Aquino', 'female', 'non_student', 'Maligaya', 'teresa.aquino@email.com'],
        ];

        $statuses = [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
            ApplicationStatus::Incomplete,
            ApplicationStatus::UnderEvaluation,
            ApplicationStatus::ForApproval,
            ApplicationStatus::Rejected,
            ApplicationStatus::ForRevision,
            ApplicationStatus::ScheduledForRelease,
            ApplicationStatus::Released,
            ApplicationStatus::Completed,
            ApplicationStatus::UnderEvaluation,
            ApplicationStatus::Approved,
        ];

        $applicants = [];
        foreach ($people as $index => [$name, $sex, $type, $barangay, $email]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'role_id' => $roles['applicant'],
                    'name' => $name,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $applicant = Applicant::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'applicant_no' => sprintf('BEN-2026-%06d', $index + 1),
                    'full_name' => $name,
                    'date_of_birth' => now()->subYears($type === 'student' ? rand(16, 22) : rand(28, 62))->subDays(rand(0, 300)),
                    'sex' => $sex,
                    'contact_number' => '09'.rand(100000000, 999999999),
                    'email' => $email,
                ]
            );

            $applicant->profile()->updateOrCreate(
                ['applicant_id' => $applicant->id],
                [
                    'beneficiary_type' => $type === 'student' ? BeneficiaryType::Student : BeneficiaryType::NonStudent,
                    'school_name' => $type === 'student' ? 'Nabua National High School' : null,
                    'course_or_program' => $type === 'student' ? 'General Academic Strand' : null,
                    'year_level' => $type === 'student' ? 'Grade 12' : null,
                ]
            );

            $applicant->addresses()->updateOrCreate(
                ['applicant_id' => $applicant->id, 'is_primary' => true],
                [
                    'street' => ($index + 12).' Mabini Street',
                    'barangay' => $barangay,
                    'municipality' => 'Nabua',
                    'province' => 'Camarines Sur',
                    'is_primary' => true,
                ]
            );

            $applicants[] = $applicant->load(['user', 'profile', 'primaryAddress']);
        }

        foreach ($applicants as $index => $applicant) {
            $eligible = $programs->filter(function ($program) use ($applicant) {
                return $program->acceptsBeneficiary($applicant->beneficiaryType());
            })->values();

            $program = $eligible[$index % $eligible->count()];
            $status = $statuses[$index % count($statuses)];
            $submittedAt = now()->subDays(rand(2, 80));

            $application = Application::query()->updateOrCreate(
                [
                    'applicant_id' => $applicant->id,
                    'assistance_program_id' => $program->id,
                ],
                [
                    'application_no' => sprintf('CAMS-2026-%06d', $index + 1),
                    'status' => $status,
                    'current_step' => 5,
                    'submitted_at' => $submittedAt,
                    'assigned_staff_id' => in_array($status, [
                        ApplicationStatus::ForApproval,
                        ApplicationStatus::Approved,
                        ApplicationStatus::ScheduledForRelease,
                        ApplicationStatus::Released,
                        ApplicationStatus::Completed,
                        ApplicationStatus::Rejected,
                    ], true) ? $users['administrator']->id : $users['staff']->id,
                    'approved_amount' => in_array($status, [
                        ApplicationStatus::Approved,
                        ApplicationStatus::ScheduledForRelease,
                        ApplicationStatus::Released,
                        ApplicationStatus::Completed,
                    ], true) ? ($program->amount ?? 3000) : null,
                    'remarks' => $status === ApplicationStatus::Rejected ? 'Does not meet documentary and eligibility requirements.' : null,
                ]
            );

            foreach ($program->formFields as $field) {
                $application->answers()->updateOrCreate(
                    ['field_name' => $field->name],
                    [
                        'program_form_field_id' => $field->id,
                        'field_label' => $field->label,
                        'value' => $this->sampleAnswer($field->name, $applicant, $program),
                    ]
                );
            }

            foreach ($program->requirements as $requirement) {
                $path = 'documents/'.$application->id.'/'.Str::slug($requirement->name).'.txt';
                $ocrText = $this->sampleOcrText($requirement->name, $applicant, $application);
                DocumentFiles::put($path, $ocrText);

                $document = $application->documents()->updateOrCreate(
                    ['program_requirement_id' => $requirement->id],
                    [
                        'requirement_name' => $requirement->name,
                        'file_path' => $path,
                        'original_name' => Str::slug($requirement->name).'.txt',
                        'mime_type' => 'text/plain',
                        'file_size' => strlen($ocrText),
                        'uploaded_at' => $submittedAt,
                    ]
                );

                $document->ocrResult()->updateOrCreate(
                    ['document_submission_id' => $document->id],
                    [
                        'status' => 'processed',
                        'raw_text' => $ocrText,
                        'expected_type' => OcrFields::typeForName($requirement->name),
                        'detected_type' => OcrFields::typeForName($requirement->name),
                        'type_matches' => true,
                        'overall_status' => 'extracted',
                        'summary' => 'Text extracted from the uploaded document.',
                        'processed_at' => $submittedAt,
                    ]
                );

                $docStatus = match ($status) {
                    ApplicationStatus::Incomplete, ApplicationStatus::ForRevision => $requirement->sort_order === 1
                        ? DocumentVerificationStatus::RevisionRequested
                        : DocumentVerificationStatus::Pending,
                    ApplicationStatus::Submitted, ApplicationStatus::UnderVerification => DocumentVerificationStatus::Pending,
                    ApplicationStatus::Rejected => DocumentVerificationStatus::Rejected,
                    default => DocumentVerificationStatus::Verified,
                };

                $document->verification()->updateOrCreate(
                    ['document_submission_id' => $document->id],
                    [
                        'status' => $docStatus,
                        'verified_by' => $docStatus === DocumentVerificationStatus::Pending ? null : $users['staff']->id,
                        'verified_at' => $docStatus === DocumentVerificationStatus::Pending ? null : $submittedAt->copy()->addDay(),
                        'remarks' => $docStatus === DocumentVerificationStatus::Verified ? 'Document is authentic and sufficient.' : null,
                    ]
                );
            }

            $history = [
                [null, ApplicationStatus::Draft, $submittedAt->copy()->subDays(1), $applicant->user_id, 'Draft created.'],
                [ApplicationStatus::Draft, ApplicationStatus::Submitted, $submittedAt, $applicant->user_id, 'Application submitted.'],
            ];

            if ($status->timelineIndex() >= 1 || in_array($status, [ApplicationStatus::Incomplete, ApplicationStatus::UnderEvaluation, ApplicationStatus::ForApproval, ApplicationStatus::ForRevision], true)) {
                $history[] = [ApplicationStatus::Submitted, ApplicationStatus::UnderVerification, $submittedAt->copy()->addDay(), $users['staff']->id, 'Documents under verification.'];
            }
            if (in_array($status, [ApplicationStatus::UnderEvaluation, ApplicationStatus::ForApproval, ApplicationStatus::Approved, ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed, ApplicationStatus::Rejected], true)) {
                $history[] = [ApplicationStatus::UnderVerification, ApplicationStatus::UnderEvaluation, $submittedAt->copy()->addDays(2), $users['staff']->id, 'Verification marked as done.'];
            }
            if (in_array($status, [ApplicationStatus::ForApproval, ApplicationStatus::Approved, ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed, ApplicationStatus::Rejected], true)) {
                $history[] = [ApplicationStatus::UnderEvaluation, ApplicationStatus::ForApproval, $submittedAt->copy()->addDays(3), $users['staff']->id, 'Evaluation completed.'];
            }
            if (in_array($status, [ApplicationStatus::Approved, ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed], true)) {
                $history[] = [ApplicationStatus::ForApproval, ApplicationStatus::Approved, $submittedAt->copy()->addDays(4), $users['administrator']->id, 'Approved for assistance.'];
            }
            if ($status === ApplicationStatus::Rejected) {
                $history[] = [ApplicationStatus::ForApproval, ApplicationStatus::Rejected, $submittedAt->copy()->addDays(4), $users['administrator']->id, 'Rejected after evaluation.'];
            }
            if ($status === ApplicationStatus::Incomplete) {
                $history[] = [ApplicationStatus::UnderVerification, ApplicationStatus::Incomplete, $submittedAt->copy()->addDays(2), $users['staff']->id, 'Missing or insufficient document.'];
            }
            if ($status === ApplicationStatus::ForRevision) {
                $history[] = [ApplicationStatus::UnderEvaluation, ApplicationStatus::ForRevision, $submittedAt->copy()->addDays(3), $users['staff']->id, 'Returned for revision.'];
            }
            if (in_array($status, [ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed], true)) {
                $history[] = [ApplicationStatus::Approved, ApplicationStatus::ScheduledForRelease, $submittedAt->copy()->addDays(6), $users['staff']->id, 'Release scheduled.'];
            }
            if (in_array($status, [ApplicationStatus::Released, ApplicationStatus::Completed], true)) {
                $history[] = [ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, $submittedAt->copy()->addDays(8), $users['staff']->id, 'Assistance released.'];
            }
            if ($status === ApplicationStatus::Completed) {
                $history[] = [ApplicationStatus::Released, ApplicationStatus::Completed, $submittedAt->copy()->addDays(8)->addHours(2), $users['staff']->id, 'Claim verified.'];
            }

            $application->statusHistory()->delete();
            foreach ($history as [$from, $to, $at, $userId, $remarks]) {
                $application->statusHistory()->create([
                    'from_status' => $from?->value,
                    'to_status' => $to->value,
                    'user_id' => $userId,
                    'remarks' => $remarks,
                    'created_at' => $at,
                ]);
            }

            if (in_array($status, [ApplicationStatus::UnderEvaluation, ApplicationStatus::ForApproval, ApplicationStatus::Approved, ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed, ApplicationStatus::Rejected], true)) {
                $application->evaluations()->delete();
                $application->evaluations()->create([
                    'evaluator_id' => $users['staff']->id,
                    'eligibility_passed' => $status !== ApplicationStatus::Rejected,
                    'documents_complete' => true,
                    'assessment' => 'Applicant documents and eligibility were reviewed against program guidelines.',
                    'recommendation' => $status === ApplicationStatus::Rejected ? 'rejection' : 'approval',
                    'recommended_amount' => $program->amount ?? 3000,
                    'remarks' => $status === ApplicationStatus::Rejected ? 'Insufficient basis for approval.' : 'Recommended for approval.',
                    'evaluated_at' => $submittedAt->copy()->addDays(3),
                ]);
            }

            if (in_array($status, [ApplicationStatus::Approved, ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed, ApplicationStatus::Rejected], true)) {
                $application->approvals()->delete();
                $application->approvals()->create([
                    'officer_id' => $users['staff']->id,
                    'decision' => $status === ApplicationStatus::Rejected ? 'rejected' : 'approved',
                    'approved_amount' => $status === ApplicationStatus::Rejected ? null : ($program->amount ?? 3000),
                    'remarks' => $status === ApplicationStatus::Rejected ? 'Rejected.' : 'Approved in accordance with program guidelines.',
                    'decided_at' => $submittedAt->copy()->addDays(4),
                ]);
            }

            if (in_array($status, [ApplicationStatus::ScheduledForRelease, ApplicationStatus::Released, ApplicationStatus::Completed], true)) {
                $application->releaseSchedules()->delete();
                $schedule = $application->releaseSchedules()->create([
                    'release_date' => $submittedAt->copy()->addDays(10)->toDateString(),
                    'release_location' => 'MSWDO Window 2, Nabua Local Government Center',
                    'release_method' => 'cash',
                    'status' => $status === ApplicationStatus::ScheduledForRelease ? 'scheduled' : 'completed',
                    'scheduled_by' => $users['staff']->id,
                    'notes' => 'Bring one valid ID and the application stub.',
                ]);

                if (in_array($status, [ApplicationStatus::Released, ApplicationStatus::Completed], true)) {
                    $application->releases()->delete();
                    $release = $application->releases()->create([
                        'release_schedule_id' => $schedule->id,
                        'amount' => $program->amount ?? 3000,
                        'released_at' => $submittedAt->copy()->addDays(10),
                        'released_by' => $users['staff']->id,
                        'reference_no' => sprintf('REL-2026-%06d', $index + 1),
                        'verification_code' => strtoupper(Str::random(10)),
                        'remarks' => 'Released over the counter.',
                    ]);

                    if ($status === ApplicationStatus::Completed) {
                        $release->verifications()->delete();
                        $release->verifications()->create([
                            'verified_by' => $users['staff']->id,
                            'verified_at' => $submittedAt->copy()->addDays(10)->addHour(),
                            'result' => 'valid',
                            'lookup_method' => 'reference_no',
                            'remarks' => 'Beneficiary presented valid ID.',
                        ]);
                    }
                }
            }

            SystemNotification::query()->updateOrCreate(
                ['user_id' => $applicant->user_id, 'application_id' => $application->id, 'title' => 'Application submitted'],
                [
                    'body' => 'Your application '.$application->application_no.' has been recorded in the Cash Assistance Management System.',
                    'type' => 'info',
                    'read_at' => $index % 3 === 0 ? null : now()->subDay(),
                ]
            );
        }

        // Extra applications across months for charts
        $extraStatuses = [ApplicationStatus::Approved, ApplicationStatus::Completed, ApplicationStatus::Rejected, ApplicationStatus::Submitted];
        for ($i = 0; $i < 18; $i++) {
            $applicant = $applicants[$i % count($applicants)];
            $program = $programs[$i % $programs->count()];
            $month = now()->subMonths($i % 8);
            Application::query()->firstOrCreate(
                ['application_no' => sprintf('CAMS-2026-%06d', 100 + $i)],
                [
                    'applicant_id' => $applicant->id,
                    'assistance_program_id' => $program->id,
                    'status' => $extraStatuses[$i % count($extraStatuses)],
                    'current_step' => 5,
                    'submitted_at' => $month->copy()->addDays(rand(1, 20)),
                    'assigned_staff_id' => $users['staff']->id,
                    'approved_amount' => $i % 2 === 0 ? ($program->amount ?? 2500) : null,
                    'created_at' => $month,
                    'updated_at' => $month,
                ]
            );
        }

        $admin = $users['administrator'];
        $announcements = [
            ['program', 'Educational Assistance now open for SY 2026-2027', 'Qualified students may now file applications for Educational Assistance. Please prepare school ID, certificate of enrollment, and proof of residency.'],
            ['application_schedule', 'Walk-in application hours', 'Walk-in filing at the MSWDO is from Monday to Friday, 8:00 AM to 5:00 PM, excluding holidays. Online filing is available at all times.'],
            ['release_schedule', 'September cash assistance release schedule', 'Approved beneficiaries scheduled for cash release this month are advised to proceed to MSWDO Window 2 with one valid ID.'],
            ['requirement', 'Updated documentary requirements for Medical Assistance', 'Hospital bills must be issued within the last 60 days. Photocopies must be clear and complete.'],
            ['notice', 'Beware of fixers', 'All services of the Municipal Social Welfare and Development Office are free of charge. Do not give money to anyone claiming they can expedite your application.'],
        ];

        foreach ($announcements as $i => [$type, $title, $body]) {
            \App\Models\Announcement::query()->updateOrCreate(
                ['title' => $title],
                [
                    'type' => $type,
                    'body' => $body,
                    'is_published' => true,
                    'published_at' => now()->subDays(8 - $i),
                    'author_id' => $admin->id,
                ]
            );
        }

        $settings = [
            'agency' => config('cams.agency'),
            'lgu' => config('cams.lgu'),
            'province' => config('cams.province'),
            'address' => config('cams.address'),
            'phone' => config('cams.phone'),
            'email' => config('cams.email'),
            'office_hours' => config('cams.office_hours'),
        ];
        foreach ($settings as $key => $value) {
            SystemSetting::setValue($key, $value, 'general');
        }

        AuditLog::query()->create([
            'user_id' => $users['administrator']->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'CAMS Seeder',
            'description' => 'System seed completed. Super administrator account initialized.',
            'created_at' => now(),
        ]);
    }

    private function sampleAnswer(string $name, Applicant $applicant, AssistanceProgram $program): string
    {
        return match ($name) {
            'school_name' => $applicant->profile?->school_name ?: 'Camarines Sur Polytechnic Colleges',
            'education_level' => 'Senior High School',
            'course_or_program' => $applicant->profile?->course_or_program ?: 'General Academic Strand',
            'year_level', 'grade_level' => $applicant->profile?->year_level ?: 'Grade 12',
            'student_id_no' => 'STU-'.str_pad((string) $applicant->id, 5, '0', STR_PAD_LEFT),
            'purpose' => 'To support school-related expenses for the current term.',
            'tuition_amount' => '7500',
            'term' => '1st Semester SY 2026-2027',
            'supplies_needed' => 'Notebooks, writing materials, and printing of modules.',
            'school_location' => 'Iriga City',
            'usual_transport' => 'Jeepney',
            'estimated_daily_fare' => '80',
            'hospital_name' => 'Nabua District Hospital',
            'diagnosis' => 'Community-acquired pneumonia, for outpatient follow-up.',
            'confinement_date' => now()->subWeeks(3)->toDateString(),
            'estimated_expense' => '8500',
            'patient_relationship' => 'Self',
            'deceased_name' => 'Sample Relative',
            'relationship' => 'Parent',
            'date_of_death' => now()->subWeeks(2)->toDateString(),
            'funeral_parlor' => 'Nabua Memorial Homes',
            'emergency_type' => 'Loss of Income',
            'incident_date' => now()->subWeeks(1)->toDateString(),
            'description' => 'Household experienced sudden loss of income and requires short-term support.',
            'requested_amount' => '4000',
            'household_size' => '5',
            'reason' => 'Insufficient means to purchase food for the household this month.',
            'livelihood_type' => 'Sari-sari store',
            'business_location' => $applicant->primaryAddress?->barangay ?: 'Poblacion',
            'capital_needed' => '10000',
            'experience' => 'Previously operated a small home-based store.',
            default => 'Provided',
        };
    }

    private function sampleOcrText(string $requirementName, Applicant $applicant, Application $application): string
    {
        $address = $applicant->fullAddress();
        $name = $applicant->full_name;
        $school = $applicant->profile?->school_name ?: 'Nabua National High School';
        $haystack = Str::lower($requirementName);
        $birthDate = $applicant->date_of_birth?->format('F d, Y') ?: 'January 1, 2000';

        return match (true) {
            str_contains($haystack, 'valid id') || str_contains($haystack, 'identification') || str_contains($haystack, 'claimant') => implode("\n", [
                'Republic of the Philippines',
                'Philippine National ID',
                "Full Name: {$name}",
                "Date of Birth: {$birthDate}",
                "Address: {$address}",
            ]),
            str_contains($haystack, 'school id') => implode("\n", [
                'School Identification Card',
                "Student Name: {$name}",
                "School: {$school}",
                'Student Number: STU-'.str_pad((string) $applicant->id, 5, '0', STR_PAD_LEFT),
            ]),
            str_contains($haystack, 'enrollment') => implode("\n", [
                'Certificate of Enrollment',
                "This is to certify that {$name} is currently enrolled at {$school}.",
                'School Year 2026-2027',
            ]),
            str_contains($haystack, 'residency') || str_contains($haystack, 'barangay') => implode("\n", [
                'Barangay Certificate',
                "This certifies that {$name} is a bona fide resident of {$address}, Municipality of Nabua, Camarines Sur.",
            ]),
            str_contains($haystack, 'medical') => implode("\n", [
                'Medical Certificate',
                "Patient: {$name}",
                'Diagnosis: Community-acquired pneumonia',
                'Physician: Dr. Sample',
            ]),
            str_contains($haystack, 'hospital') || str_contains($haystack, 'bill') => implode("\n", [
                'Hospital Bill',
                "Patient: {$name}",
                'Hospital: Nabua District Hospital',
                'Amount: 8500',
            ]),
            str_contains($haystack, 'statement') || str_contains($haystack, 'tuition') => implode("\n", [
                'Statement of Account',
                "Student: {$name}",
                "School: {$school}",
                'Tuition Assessment: 7500',
                'Outstanding balance for the current term.',
            ]),
            str_contains($haystack, 'death') => implode("\n", [
                'Death Certificate',
                'Deceased: Sample Relative',
                "Claimant: {$name}",
                "Address: {$address}",
            ]),
            str_contains($haystack, 'funeral') => implode("\n", [
                'Funeral Contract',
                "Client: {$name}",
                'Funeral Parlor: Nabua Memorial Homes',
            ]),
            str_contains($haystack, 'livelihood') => implode("\n", [
                'Livelihood Proposal',
                "Proponent: {$name}",
                'Proposed livelihood: Sari-sari store in Nabua',
            ]),
            str_contains($haystack, 'indigency') => implode("\n", [
                'Barangay Certificate of Indigency',
                "This certifies that {$name} of {$address} is an indigent resident of Nabua.",
            ]),
            str_contains($haystack, 'incident') || str_contains($haystack, 'emergency') => implode("\n", [
                'Incident Report',
                "Name: {$name}",
                'Nature of emergency: sudden loss of income',
                "Address: {$address}",
            ]),
            default => implode("\n", [
                "Official document for {$requirementName}",
                "Application {$application->application_no}",
                "Name: {$name}",
                "Address: {$address}",
                'Municipality of Nabua',
            ]),
        };
    }
}
