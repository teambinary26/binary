<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrExtractedField extends Model
{
    protected $fillable = [
        'ocr_result_id',
        'field_key',
        'field_label',
        'expected_value',
        'extracted_value',
        'corrected_value',
        'match_status',
        'match_score',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'integer',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(OcrResult::class, 'ocr_result_id');
    }

    public function displayValue(): ?string
    {
        return $this->corrected_value ?: $this->extracted_value;
    }
}
