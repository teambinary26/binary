<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        string $action,
        string $description,
        ?Application $application = null,
        ?Model $subject = null,
        ?User $user = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();
        $user ??= $request?->user();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'application_id' => $application?->id,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
