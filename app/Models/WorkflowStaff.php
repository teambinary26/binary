<?php

namespace App\Models;

use App\Enums\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStaff extends Model
{
    protected $table = 'workflow_staff';

    protected $fillable = [
        'workflow_step',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'workflow_step' => WorkflowStep::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function staffForStep(WorkflowStep $step): ?User
    {
        return static::staffMembersForStep($step)->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function staffMembersForStep(WorkflowStep $step): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('workflow_step', $step->value)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();
    }

    public static function namesForStep(WorkflowStep $step): ?string
    {
        $names = static::staffMembersForStep($step)->pluck('name')->filter()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    public static function isAssignedToStep(int $userId, WorkflowStep $step): bool
    {
        return static::query()
            ->where('workflow_step', $step->value)
            ->where('user_id', $userId)
            ->exists();
    }

    public static function allAssignments(): \Illuminate\Support\Collection
    {
        return static::query()->with('user')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, WorkflowStep>
     */
    public static function stepsForUser(int $userId): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('user_id', $userId)
            ->get()
            ->pluck('workflow_step')
            ->filter()
            ->values();
    }
}
