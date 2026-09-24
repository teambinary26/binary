<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\OcrResult;
use App\Models\AssistanceRelease;
use App\Models\ReleaseSchedule;
use App\Services\EligibilityAssessmentService;
use Illuminate\Pagination\LengthAwarePaginator;

class CamData
{
    public static function program(AssistanceProgram $program, bool $detail = false): array
    {
        $data = [
            'id' => $program->id,
            'name' => $program->name,
            'code' => $program->code,
            'slug' => $program->slug,
            'description' => $program->description,
            'eligibility' => $program->eligibility,
            'beneficiary_type' => $program->beneficiary_type->value,
            'beneficiary_label' => $program->beneficiary_type->label(),
            'amount_type' => $program->amount_type,
            'amount' => $program->amount,
            'amount_max' => $program->amount_max,
            'amount_display' => $program->amountDisplay(),
            'is_open' => $program->is_open,
            'is_currently_open' => $program->isCurrentlyOpen(),
            'availability_label' => $program->availabilityLabel(),
            'open_from' => gov_date($program->open_from),
            'open_until' => gov_date($program->open_until),
            'open_from_raw' => $program->open_from?->format('Y-m-d'),
            'open_until_raw' => $program->open_until?->format('Y-m-d'),
            'slot_limit' => $program->slot_limit,
            'applications_count' => $program->applications_count ?? $program->applications()->count(),
            'can_delete' => ($program->applications_count ?? $program->applications()->count()) === 0,
            'category' => $program->category ? [
                'id' => $program->category->id,
                'name' => $program->category->name,
                'group' => $program->category->group,
                'group_label' => $program->category->groupLabel(),
            ] : null,
        ];

        if ($detail) {
            $data['requirements'] = $program->requirements->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'is_required' => $r->is_required,
                'ocr_fields' => $r->ocr_fields ?: [],
            ])->values();
            $data['eligibility_rules'] = $program->eligibilityRules->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->label,
                'field' => $r->field,
                'operator' => $r->operator,
                'value' => $r->value,
                'check_mode' => $r->check_mode ?: 'ocr',
            ])->values();
            $data['form_fields'] = $program->formFields->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'label' => $f->label,
                'type' => $f->type,
                'options' => $f->options,
                'is_required' => $f->is_required,
                'help_text' => $f->help_text,
            ])->values();
        }

        return $data;
    }

    public static function applicationRow(Application $application): array
    {
        return [
            'id' => $application->id,
            'application_no' => $application->application_no,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'status_tone' => $application->status->tone(),
            'status_description' => $application->status->description(),
            'submitted_at' => gov_date($application->submitted_at ?? $application->created_at),
            'submitted_at_full' => gov_datetime($application->submitted_at ?? $application->created_at),
            'approved_amount' => $application->approved_amount,
            'approved_amount_formatted' => peso($application->approved_amount),
            'can_edit' => $application->canBeEditedByApplicant(),
            'continue_path' => $application->canBeEditedByApplicant() ? $application->continuePath() : null,
            'applicant' => $application->applicant ? [
                'id' => $application->applicant->id,
                'full_name' => $application->applicant->full_name,
                'applicant_no' => $application->applicant->applicant_no,
                'barangay' => $application->applicant->primaryAddress?->barangay,
            ] : null,
            'program' => $application->program ? [
                'id' => $application->program->id,
                'name' => $application->program->name,
                'code' => $application->program->code,
                'slug' => $application->program->slug,
                'amount' => $application->program->amount,
                'amount_display' => $application->program->amountDisplay(),
                'category_name' => $application->program->category?->name,
            ] : null,
            'assigned_staff' => $application->assignedStaff?->name,
            'assigned_staff_id' => $application->assigned_staff_id,
            'recommended_amount_formatted' => peso($application->latestEvaluation?->recommended_amount),
            'recommendation_label' => $application->latestEvaluation?->recommendationLabel(),
        ];
    }

    public static function applicationDetail(Application $application): array
    {
        $row = self::applicationRow($application);
        $timeline = collect(ApplicationStatus::timeline())->values()->map(function (ApplicationStatus $step, int $index) use ($application) {
            $current = $application->status;
            $state = 'upcoming';
            if ($current === ApplicationStatus::Rejected && $index >= 4) {
                $state = $index === 4 ? 'rejected' : 'upcoming';
            } elseif ($current === ApplicationStatus::Cancelled) {
                $state = 'upcoming';
            } elseif ($current->timelineIndex() > $index) {
                $state = 'done';
            } elseif ($current === $step
                || ($current === ApplicationStatus::Incomplete && $step === ApplicationStatus::UnderVerification)
                || ($current === ApplicationStatus::ForRevision && $step === ApplicationStatus::UnderEvaluation)) {
                $state = 'current';
            }

            return [
                'index' => $index + 1,
                'value' => $step->value,
                'label' => $state === 'rejected' ? 'Rejected' : $step->label(),
                'description' => $step->description(),
                'state' => $state,
            ];
        });

        return array_merge($row, [
            'timeline' => $timeline,
            'needs_initial_review' => $application->status === ApplicationStatus::Draft,
            'answers' => self::submittedInformation($application),
            'documents' => $application->documents->map(fn ($d) => [
                'id' => $d->id,
                'requirement_id' => $d->program_requirement_id,
                'requirement_name' => $d->requirement_name,
                'side' => $d->side ?: 'front',
                'original_name' => $d->original_name,
                'url' => DocumentFiles::url($d->file_path),
                'is_image' => DocumentFiles::isImage($d->mime_type, $d->original_name),
                'is_pdf' => DocumentFiles::isPdf($d->mime_type, $d->original_name),
                'is_text' => DocumentFiles::isText($d->mime_type, $d->original_name),
                'uploaded_at' => gov_datetime($d->uploaded_at),
                'status' => $d->verification?->status->value,
                'status_label' => $d->verification?->status->label() ?? 'Pending Verification',
                'status_tone' => $d->verification?->status->tone() ?? 'neutral',
                'verified_by' => $d->verification?->verifier?->name,
                'verified_at' => gov_datetime($d->verification?->verified_at),
                'remarks' => $d->verification?->remarks,
                'ocr' => self::ocrResult($d->ocrResult),
            ])->values(),
            'history' => $application->statusHistory->map(fn ($h) => [
                'to_status' => $h->to_status?->label(),
                'user' => $h->user?->name,
                'remarks' => $h->remarks,
                'created_at' => gov_datetime($h->created_at),
            ])->values(),
            'program_detail' => $application->program ? self::program($application->program, true) : null,
            'eligibility_assessment' => app(EligibilityAssessmentService::class)->assess($application),
            'applicant_detail' => $application->applicant ? self::applicant($application->applicant) : null,
            'latest_evaluation' => $application->latestEvaluation ? [
                'recommendation' => $application->latestEvaluation->recommendation,
                'recommendation_label' => $application->latestEvaluation->recommendationLabel(),
                'assessment' => $application->latestEvaluation->assessment,
                'remarks' => $application->latestEvaluation->remarks,
                'recommended_amount' => $application->latestEvaluation->recommended_amount,
                'recommended_amount_formatted' => peso($application->latestEvaluation->recommended_amount),
                'evaluator' => $application->latestEvaluation->evaluator?->name,
                'evaluated_at' => gov_datetime($application->latestEvaluation->evaluated_at),
                'eligibility_passed' => $application->latestEvaluation->eligibility_passed,
                'eligibility_checks' => $application->latestEvaluation->eligibility_checks ?: [],
                'documents_complete' => $application->latestEvaluation->documents_complete,
            ] : null,
            'latest_approval' => $application->latestApproval ? [
                'decision' => $application->latestApproval->decision,
                'decision_label' => $application->latestApproval->decisionLabel(),
                'officer' => $application->latestApproval->officer?->name,
                'remarks' => $application->latestApproval->remarks,
                'decided_at' => gov_datetime($application->latestApproval->decided_at),
            ] : null,
            'latest_schedule' => $application->latestSchedule ? self::schedule($application->latestSchedule) : null,
            'latest_release' => $application->latestRelease ? [
                'reference_no' => $application->latestRelease->reference_no,
                'verification_code' => $application->latestRelease->verification_code,
                'amount_formatted' => peso($application->latestRelease->amount),
                'released_at' => gov_datetime($application->latestRelease->released_at),
            ] : null,
            'required_documents_verified' => $application->requiredDocumentsVerified(),
            'can_complete_verification' => $application->canCompleteVerification(),
            'can_forward_to_approval' => $application->canForwardToApproval(),
            'needs_document_action' => $application->needsDocumentAction(),
            'revision_documents' => $application->documentsNeedingAction()->map(fn ($document) => [
                'id' => $document->id,
                'requirement_id' => $document->program_requirement_id,
                'requirement_name' => $document->requirement_name,
                'status' => $document->verification?->status->value,
                'status_label' => $document->verification?->status->label(),
                'remarks' => $document->verification?->remarks,
            ])->values(),
            'is_in_verification' => in_array($application->status, [
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderVerification,
                ApplicationStatus::Incomplete,
                ApplicationStatus::ForRevision,
            ], true),
            'is_in_evaluation' => $application->status === ApplicationStatus::UnderEvaluation,
            'is_in_approval' => $application->status === ApplicationStatus::ForApproval,
        ]);
    }

    public static function applicant(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'applicant_no' => $applicant->applicant_no,
            'full_name' => $applicant->full_name,
            'date_of_birth' => gov_date($applicant->date_of_birth),
            'date_of_birth_raw' => $applicant->date_of_birth?->format('Y-m-d'),
            'sex' => $applicant->sex,
            'contact_number' => $applicant->contact_number,
            'email' => $applicant->email,
            'full_address' => $applicant->fullAddress(),
            'beneficiary_type' => $applicant->profile?->beneficiary_type->value,
            'beneficiary_label' => $applicant->profile?->beneficiary_type->label(),
            'school_name' => $applicant->profile?->school_name,
            'course_or_program' => $applicant->profile?->course_or_program,
            'year_level' => $applicant->profile?->year_level,
            'mother_name' => $applicant->profile?->mother_name,
            'mother_occupation' => $applicant->profile?->mother_occupation,
            'father_name' => $applicant->profile?->father_name,
            'father_occupation' => $applicant->profile?->father_occupation,
            'is_pwd' => (bool) ($applicant->profile?->is_pwd),
            'pwd_type' => $applicant->profile?->pwd_type,
            'pwd_type_label' => $applicant->profile?->pwd_type
                ? (config('cams.pwd_types.'.$applicant->profile->pwd_type) ?? $applicant->profile->pwd_type)
                : null,
            'pwd_type_detail' => $applicant->profile?->pwd_type_detail,
            'street' => $applicant->primaryAddress?->street,
            'barangay' => $applicant->primaryAddress?->barangay,
            'municipality' => $applicant->primaryAddress?->municipality,
            'province' => $applicant->primaryAddress?->province,
            'applications_count' => $applicant->applications_count ?? null,
            'account_active' => (bool) $applicant->user?->is_active,
            'account_pending' => (bool) $applicant->user?->pending_account,
        ];
    }

    public static function schedule(ReleaseSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'release_date' => gov_date($schedule->release_date),
            'release_date_input' => optional($schedule->release_date)->format('Y-m-d'),
            'release_location' => $schedule->release_location,
            'release_method' => $schedule->release_method,
            'method_label' => $schedule->methodLabel(),
            'status' => $schedule->status,
            'notes' => $schedule->notes,
            'application' => $schedule->application ? self::applicationRow($schedule->application) : null,
        ];
    }

    public static function release(AssistanceRelease $release): array
    {
        return [
            'id' => $release->id,
            'reference_no' => $release->reference_no,
            'verification_code' => $release->verification_code,
            'amount_formatted' => peso($release->amount),
            'released_at' => gov_datetime($release->released_at),
            'officer' => $release->officer?->name,
            'application' => $release->application ? self::applicationRow($release->application) : null,
        ];
    }

    public static function submittedInformation(Application $application): \Illuminate\Support\Collection
    {
        $detail = $application->applicant ? self::applicant($application->applicant) : [];
        $pwdValue = ! empty($detail['is_pwd'])
            ? trim(($detail['pwd_type_label'] ?? 'Yes').(filled($detail['pwd_type_detail'] ?? null) ? ' — '.$detail['pwd_type_detail'] : ''))
            : 'No';

        $rows = collect([
            ['field_name' => 'date_applied', 'field_label' => 'Date applied', 'value' => gov_datetime($application->submitted_at ?? $application->created_at)],
            ['field_name' => 'full_name', 'field_label' => 'Full name', 'value' => $detail['full_name'] ?? null],
            ['field_name' => 'date_of_birth', 'field_label' => 'Date of birth', 'value' => $detail['date_of_birth'] ?? null],
            ['field_name' => 'sex', 'field_label' => 'Sex', 'value' => $detail['sex'] ?? null],
            ['field_name' => 'contact_number', 'field_label' => 'Contact number', 'value' => $detail['contact_number'] ?? null],
            ['field_name' => 'email', 'field_label' => 'Email', 'value' => $detail['email'] ?? null],
            ['field_name' => 'street', 'field_label' => 'Address (house no. / street)', 'value' => $detail['street'] ?? null],
            ['field_name' => 'barangay', 'field_label' => 'Barangay', 'value' => $detail['barangay'] ?? null],
            ['field_name' => 'municipality', 'field_label' => 'Municipality', 'value' => $detail['municipality'] ?? null],
            ['field_name' => 'province', 'field_label' => 'Province', 'value' => $detail['province'] ?? null],
            ['field_name' => 'mother_name', 'field_label' => "Mother's name", 'value' => $detail['mother_name'] ?? null],
            ['field_name' => 'mother_occupation', 'field_label' => "Mother's occupation", 'value' => $detail['mother_occupation'] ?? null],
            ['field_name' => 'father_name', 'field_label' => "Father's name", 'value' => $detail['father_name'] ?? null],
            ['field_name' => 'father_occupation', 'field_label' => "Father's occupation", 'value' => $detail['father_occupation'] ?? null],
            ['field_name' => 'is_pwd', 'field_label' => 'Person with disability (PWD)', 'value' => $pwdValue],
            ['field_name' => 'beneficiary_type', 'field_label' => 'Beneficiary type', 'value' => $detail['beneficiary_label'] ?? null],
            ['field_name' => 'school_name', 'field_label' => 'Name of school', 'value' => $detail['school_name'] ?? null],
            ['field_name' => 'course_or_program', 'field_label' => 'Course / program', 'value' => $detail['course_or_program'] ?? null],
            ['field_name' => 'year_level', 'field_label' => 'Year level', 'value' => $detail['year_level'] ?? null],
        ]);

        $known = $rows->pluck('field_name');

        $programAnswers = $application->answers->map(fn ($answer) => [
            'field_name' => $answer->field_name,
            'field_label' => $answer->field_label,
            'value' => $answer->value,
        ])->reject(fn ($answer) => $known->contains($answer['field_name']) || blank($answer['value']));

        return $rows
            ->concat($programAnswers)
            ->map(fn (array $row) => [
                'field_name' => $row['field_name'],
                'field_label' => $row['field_label'],
                'value' => filled($row['value']) ? $row['value'] : '—',
            ])
            ->values();
    }

    public static function ocrResult(?OcrResult $result): ?array
    {
        if (! $result) {
            return null;
        }

        $result->loadMissing('fields');

        return [
            'id' => $result->id,
            'status' => $result->status,
            'raw_text' => $result->raw_text,
            'expected_type' => $result->expected_type,
            'detected_type' => $result->detected_type,
            'type_matches' => $result->type_matches,
            'overall_score' => $result->overall_score,
            'overall_status' => $result->overall_status,
            'overall_label' => match ($result->overall_status) {
                'matched', 'review' => ($result->overall_score ?? 0).'% match',
                'mismatch' => 'Field mismatch',
                'type_mismatch' => 'Document type mismatch',
                'extracted' => 'Information extracted',
                'failed' => 'OCR failed',
                default => 'OCR pending',
            },
            'summary' => $result->summary,
            'error_message' => $result->error_message,
            'tone' => $result->tone(),
            'fields' => $result->fields->map(fn ($field) => [
                'id' => $field->id,
                'key' => $field->field_key,
                'label' => $field->field_label,
                'expected_value' => $field->expected_value,
                'extracted_value' => $field->extracted_value,
                'corrected_value' => $field->corrected_value,
                'display_value' => $field->displayValue(),
                'match_status' => $field->match_status,
                'match_score' => $field->match_score,
            ])->values(),
        ];
    }

    public static function statuses(): array
    {
        return collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values()->all();
    }

    public static function paginator(LengthAwarePaginator $paginator, callable $mapper): array
    {
        $paginator->withQueryString();

        return [
            'data' => $paginator->getCollection()->map($mapper)->values(),
            'links' => $paginator->linkCollection()->map(fn ($l) => [
                'url' => $l['url'],
                'label' => $l['label'],
                'active' => $l['active'],
            ])->values(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public static function paginateCollection(iterable $items, callable $mapper, int $perPage = 15, string $pageName = 'page'): array
    {
        $collection = collect($items)->values();
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $paginator = new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );

        return self::paginator($paginator, $mapper);
    }
}
