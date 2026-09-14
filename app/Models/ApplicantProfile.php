<?php

namespace App\Models;

use App\Enums\BeneficiaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantProfile extends Model
{
    protected $fillable = [
        'applicant_id',
        'beneficiary_type',
        'school_name',
        'course_or_program',
        'year_level',
        'mother_name',
        'mother_occupation',
        'father_name',
        'father_occupation',
        'is_pwd',
        'pwd_type',
        'pwd_type_detail',
    ];

    protected function casts(): array
    {
        return [
            'beneficiary_type' => BeneficiaryType::class,
            'is_pwd' => 'boolean',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
