<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationEvaluation extends Model
{
    protected $fillable = [
        'application_id',
        'evaluator_id',
        'eligibility_passed',
        'eligibility_checks',
        'documents_complete',
        'assessment',
        'recommendation',
        'recommended_amount',
        'remarks',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'eligibility_passed' => 'boolean',
            'eligibility_checks' => 'array',
            'documents_complete' => 'boolean',
            'recommended_amount' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function recommendationLabel(): string
    {
        return match ($this->recommendation) {
            'approval' => 'Recommend Approval',
            'rejection' => 'Recommend Rejection',
            'revision' => 'Return for Revision',
            default => ucfirst($this->recommendation),
        };
    }
}
