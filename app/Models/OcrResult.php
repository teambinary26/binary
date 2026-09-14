<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OcrResult extends Model
{
    protected $fillable = [
        'document_submission_id',
        'status',
        'raw_text',
        'expected_type',
        'detected_type',
        'type_matches',
        'overall_score',
        'overall_status',
        'summary',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'type_matches' => 'boolean',
            'overall_score' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentSubmission::class, 'document_submission_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(OcrExtractedField::class);
    }

    public function tone(): string
    {
        return match ($this->overall_status) {
            'matched' => 'success',
            'review', 'extracted' => 'info',
            'mismatch', 'type_mismatch' => 'warning',
            'failed' => 'danger',
            default => 'neutral',
        };
    }
}
