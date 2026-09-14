<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramRequirement extends Model
{
    protected $fillable = [
        'assistance_program_id',
        'name',
        'description',
        'is_required',
        'ocr_fields',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'ocr_fields' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgram::class, 'assistance_program_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DocumentSubmission::class);
    }
}
