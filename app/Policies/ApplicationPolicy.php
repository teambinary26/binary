<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        if ($user->isApplicant()) {
            return $user->applicant?->id === $application->applicant_id;
        }

        return $user->hasPermission('applications.view');
    }

    public function update(User $user, Application $application): bool
    {
        if ($user->isApplicant()) {
            return $user->applicant?->id === $application->applicant_id && $application->canBeEditedByApplicant();
        }

        return $user->hasPermission('applications.manage');
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->isAdmin();
    }

    public function acceptIncoming(User $user, Application $application): bool
    {
        return $user->hasPermission('applications.accept');
    }

    public function verify(User $user, Application $application): bool
    {
        return $user->hasPermission('applications.verify');
    }

    public function evaluate(User $user, Application $application): bool
    {
        return $user->hasPermission('applications.evaluate');
    }

    public function approve(User $user, Application $application): bool
    {
        return $user->hasPermission('applications.approve');
    }
}
