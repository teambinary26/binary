<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationApproval extends Model
{
    protected $fillable = [
        'application_id',
        'officer_id',
        'decision',
        'approved_amount',
        'remarks',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function decisionLabel(): string
    {
        return match ($this->decision) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'revision' => 'Returned for Revision',
            default => ucfirst($this->decision),
        };
    }
}
