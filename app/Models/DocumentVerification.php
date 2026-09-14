<?php

namespace App\Models;

use App\Enums\DocumentVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerification extends Model
{
    protected $fillable = [
        'document_submission_id',
        'status',
        'verified_by',
        'verified_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentSubmission::class, 'document_submission_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
