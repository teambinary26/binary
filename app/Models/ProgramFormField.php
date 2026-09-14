<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramFormField extends Model
{
    protected $fillable = [
        'assistance_program_id',
        'name',
        'label',
        'type',
        'options',
        'is_required',
        'help_text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgram::class, 'assistance_program_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class);
    }
}
