<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Support\IdRequirements;
use App\Enums\DocumentVerificationStatus;
use App\Enums\WorkflowStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    protected $fillable = [
        'application_no',
        'applicant_id',
        'assistance_program_id',
        'status',
        'current_step',
        'submitted_at',
        'assigned_staff_id',
        'approved_amount',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'approved_amount' => 'decimal:2',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgram::class, 'assistance_program_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentSubmission::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('created_at');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ApplicationEvaluation::class);
    }

    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(ApplicationEvaluation::class)->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ApplicationApproval::class);
    }

    public function latestApproval(): HasOne
    {
        return $this->hasOne(ApplicationApproval::class)->latestOfMany();
    }

    public function releaseSchedules(): HasMany
    {
        return $this->hasMany(ReleaseSchedule::class);
    }

    public function latestSchedule(): HasOne
    {
        return $this->hasOne(ReleaseSchedule::class)->latestOfMany();
    }

    public function releases(): HasMany
    {
        return $this->hasMany(AssistanceRelease::class);
    }

    public function latestRelease(): HasOne
    {
        return $this->hasOne(AssistanceRelease::class)->latestOfMany();
    }

    public function scopeStatus(Builder $query, ApplicationStatus|string $status): Builder
    {
        $value = $status instanceof ApplicationStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function canBeEditedByApplicant(): bool
    {
        if (in_array($this->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::Accepted,
            ApplicationStatus::ForRevision,
            ApplicationStatus::Incomplete,
        ], true)) {
            return true;
        }

        return $this->status === ApplicationStatus::UnderVerification
            && $this->documentsNeedingAction()->isNotEmpty();
    }

    public function canSupplyMissingDocuments(): bool
    {
        if ($this->canBeEditedByApplicant()) {
            return true;
        }

        return in_array($this->status, [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
        ], true) && $this->missingRequiredRequirements()->isNotEmpty();
    }

    public function answerMap(): array
    {
        return $this->answers->mapWithKeys(fn (ApplicationAnswer $answer) => [
            $answer->field_name => $answer->value,
        ])->all();
    }

    public function missingRequiredFormAnswers(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['program.formFields', 'answers']);

        if (! $this->program) {
            return collect();
        }

        $saved = $this->answerMap();

        return $this->program->formFields
            ->filter(fn ($field) => $field->is_required && blank($saved[$field->name] ?? null))
            ->values();
    }

    public function needsFormStep(): bool
    {
        $this->loadMissing(['program.formFields', 'answers']);

        if (! $this->program || $this->program->formFields->isEmpty()) {
            return false;
        }

        if ($this->missingRequiredFormAnswers()->isNotEmpty()) {
            return true;
        }

        return $this->current_step < 3 && $this->answers->isEmpty();
    }

    public function documentForRequirement(int $requirementId, string $side = 'front'): ?DocumentSubmission
    {
        return $this->documents->first(function (DocumentSubmission $document) use ($requirementId, $side) {
            if ((int) $document->program_requirement_id !== $requirementId) {
                return false;
            }

            $documentSide = $document->side ?: 'front';

            return $documentSide === $side;
        });
    }

    public function missingRequiredRequirements(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['program.requirements', 'documents']);

        if (! $this->program) {
            return collect();
        }

        return $this->program->requirements
            ->filter(function ($requirement) {
                if (! $requirement->is_required) {
                    return false;
                }

                if (! $this->documentForRequirement($requirement->id)) {
                    return true;
                }

                return IdRequirements::requiresBack($requirement->name)
                    && ! $this->documentForRequirement($requirement->id, 'back');
            })
            ->values();
    }

    public function continuePath(): string
    {
        if ($this->needsFormStep()) {
            return route('applicant.apply.form', $this);
        }

        if ($this->needsDocumentAction()) {
            return route('applicant.apply.documents', $this);
        }

        return route('applicant.apply.review', $this);
    }

    public function documentsNeedingAction(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['documents.verification', 'program.requirements']);

        return $this->documents
            ->filter(function (DocumentSubmission $document) {
                return in_array($document->verification?->status, [
                    DocumentVerificationStatus::RevisionRequested,
                    DocumentVerificationStatus::Rejected,
                ], true);
            })
            ->values();
    }

    public function needsDocumentAction(): bool
    {
        return $this->missingRequiredRequirements()->isNotEmpty()
            || $this->documentsNeedingAction()->isNotEmpty();
    }

    public function requiredDocumentsVerified(): bool
    {
        $this->loadMissing(['documents.verification', 'program.requirements']);

        $required = $this->program?->requirements?->where('is_required', true) ?? collect();

        if ($required->isEmpty()) {
            return $this->documents->isNotEmpty();
        }

        foreach ($required as $requirement) {
            $sides = IdRequirements::requiresBack($requirement->name) ? ['front', 'back'] : ['front'];

            foreach ($sides as $side) {
                $document = $this->documentForRequirement($requirement->id, $side);
                if (! $document || $document->verification?->status !== DocumentVerificationStatus::Verified) {
                    return false;
                }
            }
        }

        return true;
    }

    public function canCompleteVerification(): bool
    {
        return in_array($this->status, [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
            ApplicationStatus::ForRevision,
        ], true) && $this->requiredDocumentsVerified();
    }

    public function canForwardToApproval(): bool
    {
        $this->loadMissing('latestEvaluation');

        return $this->status === ApplicationStatus::UnderEvaluation
            && $this->latestEvaluation
            && in_array($this->latestEvaluation->recommendation, ['approval', 'rejection'], true);
    }

    public function currentWorkflowStep(): WorkflowStep
    {
        return match ($this->status) {
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderVerification,
            ApplicationStatus::Incomplete,
            ApplicationStatus::ForRevision => WorkflowStep::Verification,

            ApplicationStatus::UnderEvaluation => WorkflowStep::Evaluation,

            ApplicationStatus::ForApproval,
            ApplicationStatus::Approved,
            ApplicationStatus::Rejected => WorkflowStep::Approval,

            default => WorkflowStep::Verification,
        };
    }

    /**
     * @return array{previous: ?array{id: int, application_no: string}, next: ?array{id: int, application_no: string}, position: int, total: int, staff_name: ?string, step: ?string, step_label: ?string}
     */
    public function assignedQueueNavigation(?User $viewer = null, ?WorkflowStep $preferredStep = null): array
    {
        $step = $this->queueStepForViewer($viewer, $preferredStep);
        $staff = $step ? WorkflowStaff::staffForStep($step) : null;
        $staffId = $viewer && ! $viewer->isAdmin() ? $viewer->id : $staff?->id;
        $statuses = $step
            ? array_map(fn (ApplicationStatus $status) => $status->value, $step->queueStatuses())
            : [];

        $ids = $statuses === []
            ? collect()
            : static::query()
                ->whereIn('status', $statuses)
                ->when($staffId, fn (Builder $query) => $query->where('assigned_staff_id', $staffId))
                ->orderBy('application_no')
                ->orderBy('id')
                ->pluck('id');

        $total = $ids->count();
        $index = $ids->search($this->id);

        $previousId = null;
        $nextId = null;

        if ($total > 0 && $index === false) {
            $nextId = $ids->first();
            $previousId = $ids->count() > 1 ? $ids->last() : null;
        } elseif ($index !== false && $total > 1) {
            $previousId = $ids[($index - 1 + $total) % $total];
            $nextId = $ids[($index + 1) % $total];
        }

        $neighbors = $previousId || $nextId
            ? static::query()->whereIn('id', array_filter([$previousId, $nextId]))->get()->keyBy('id')
            : collect();

        $summary = function (?int $id) use ($neighbors): ?array {
            $application = $id ? $neighbors->get($id) : null;

            if (! $application) {
                return null;
            }

            return [
                'id' => $application->id,
                'application_no' => $application->application_no,
            ];
        };

        return [
            'previous' => $summary($previousId),
            'next' => $summary($nextId),
            'position' => $index === false ? 0 : $index + 1,
            'total' => $total,
            'staff_name' => $step ? WorkflowStaff::namesForStep($step) : $staff?->name,
            'step' => $step?->value,
            'step_label' => $step?->label(),
        ];
    }

    private function queueStepForViewer(?User $viewer, ?WorkflowStep $preferredStep = null): ?WorkflowStep
    {
        $assigned = $viewer
            ? WorkflowStaff::stepsForUser($viewer->id)
            : collect();

        if ($preferredStep && ($viewer?->isAdmin() || $assigned->contains($preferredStep))) {
            return $preferredStep;
        }

        if ($assigned->count() === 1) {
            return $assigned->first();
        }

        $current = $this->currentWorkflowStep();

        if ($viewer?->isAdmin() || $assigned->contains($current)) {
            return $current;
        }

        return $assigned->first();
    }
}
