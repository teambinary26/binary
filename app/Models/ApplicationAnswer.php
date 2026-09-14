<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAnswer extends Model
{
    protected $fillable = [
        'application_id',
        'program_form_field_id',
        'field_name',
        'field_label',
        'value',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ProgramFormField::class, 'program_form_field_id');
    }
}
