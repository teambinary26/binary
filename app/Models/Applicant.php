<?php

namespace App\Models;

use App\Enums\BeneficiaryType;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    protected $fillable = [
        'user_id',
        'applicant_no',
        'full_name',
        'date_of_birth',
        'sex',
        'contact_number',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ApplicantProfile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ApplicantAddress::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(ApplicantAddress::class)->where('is_primary', true);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function currentApplication(): ?Application
    {
        return $this->applications()
            ->with('program')
            ->whereIn('status', ApplicationStatus::currentValues())
            ->latest('id')
            ->first();
    }

    public function age(): int
    {
        return $this->date_of_birth?->age ?? 0;
    }

    public function beneficiaryType(): BeneficiaryType
    {
        return $this->profile?->beneficiary_type ?? BeneficiaryType::NonStudent;
    }

    public function fullAddress(): string
    {
        $address = $this->primaryAddress;

        if (! $address) {
            return '—';
        }

        return $address->street.', '.$address->barangay.', '.$address->municipality.', '.$address->province;
    }
}
