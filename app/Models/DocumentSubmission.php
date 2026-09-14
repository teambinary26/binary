<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentSubmission extends Model
{
    protected $fillable = [
        'application_id',
        'program_requirement_id',
        'requirement_name',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProgramRequirement::class, 'program_requirement_id');
    }

    public function verification(): HasOne
    {
        return $this->hasOne(DocumentVerification::class);
    }

    public function ocrResult(): HasOne
    {
        return $this->hasOne(OcrResult::class);
    }
}
