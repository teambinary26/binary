<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\BeneficiaryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssistanceProgram extends Model
{
    protected $fillable = [
        'program_category_id',
        'name',
        'code',
        'slug',
        'description',
        'eligibility',
        'beneficiary_type',
        'amount_type',
        'amount',
        'amount_max',
        'is_open',
        'open_from',
        'open_until',
        'slot_limit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'beneficiary_type' => BeneficiaryType::class,
            'amount' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'is_open' => 'boolean',
            'open_from' => 'date',
            'open_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AssistanceProgram $program) {
            if (! $program->slug) {
                $program->slug = Str::slug($program->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProgramCategory::class, 'program_category_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProgramRequirement::class)->orderBy('sort_order');
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(ProgramEligibilityRule::class)->orderBy('sort_order');
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(ProgramFormField::class)->orderBy('sort_order');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function amountDisplay(): string
    {
        if ($this->amount_type === 'up_to') {
            return 'Up to '.peso($this->amount_max ?? $this->amount, false);
        }

        if ($this->amount_type === 'variable') {
            return 'Variable / as evaluated';
        }

        return peso($this->amount, false);
    }

    public function isCurrentlyOpen(): bool
    {
        if (! $this->is_open) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->open_from && $today->lt($this->open_from->startOfDay())) {
            return false;
        }

        if ($this->open_until && $today->gt($this->open_until->endOfDay())) {
            return false;
        }

        if ($this->slot_limit) {
            $used = $this->applications()
                ->whereNotIn('status', [
                    ApplicationStatus::Draft->value,
                    ApplicationStatus::Cancelled->value,
                    ApplicationStatus::Rejected->value,
                ])->count();

            if ($used >= $this->slot_limit) {
                return false;
            }
        }

        return true;
    }

    public function availabilityLabel(): string
    {
        return $this->isCurrentlyOpen() ? 'Open for Application' : 'Closed';
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_open', true);
    }

    public function acceptsBeneficiary(BeneficiaryType $type): bool
    {
        return $this->beneficiary_type === BeneficiaryType::Both
            || $this->beneficiary_type === $type;
    }
}
