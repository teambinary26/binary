<?php

namespace App\Services;

use App\Models\Application;
use App\Models\SystemNotification;
use App\Models\User;

class NotificationService
{
    public function notify(
        User $user,
        string $title,
        string $body,
        string $type = 'info',
        ?Application $application = null,
    ): SystemNotification {
        return SystemNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'application_id' => $application?->id,
        ]);
    }

    public function notifyApplicant(Application $application, string $title, string $body, string $type = 'info'): void
    {
        $application->loadMissing('applicant.user');
        $user = $application->applicant?->user;

        if ($user) {
            $this->notify($user, $title, $body, $type, $application);
        }
    }
}
