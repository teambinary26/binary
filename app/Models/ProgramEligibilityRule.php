<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEligibilityRule extends Model
{
    protected $fillable = [
        'assistance_program_id',
        'label',
        'field',
        'operator',
        'value',
        'check_mode',
        'sort_order',
    ];

    public function isManual(): bool
    {
        return $this->check_mode === 'manual';
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgram::class, 'assistance_program_id');
    }
}
