<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistanceRelease extends Model
{
    protected $fillable = [
        'application_id',
        'release_schedule_id',
        'amount',
        'released_at',
        'released_by',
        'reference_no',
        'verification_code',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReleaseSchedule::class, 'release_schedule_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ReleaseVerification::class);
    }
}
