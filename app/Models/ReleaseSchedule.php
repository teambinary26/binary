<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReleaseSchedule extends Model
{
    protected $fillable = [
        'application_id',
        'release_date',
        'release_location',
        'release_method',
        'status',
        'scheduled_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function release(): HasOne
    {
        return $this->hasOne(AssistanceRelease::class);
    }

    public function methodLabel(): string
    {
        return config('cams.release_methods.'.$this->release_method, $this->release_method);
    }
}
