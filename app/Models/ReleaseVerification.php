<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseVerification extends Model
{
    protected $fillable = [
        'assistance_release_id',
        'verified_by',
        'verified_at',
        'result',
        'lookup_method',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(AssistanceRelease::class, 'assistance_release_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
