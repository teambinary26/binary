<?php

namespace App\Policies;

use App\Enums\WorkflowStep;
use App\Models\Application;
use App\Models\User;
use App\Models\WorkflowStaff;

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

    public function verify(User $user, Application $application): bool
    {
        if (! $user->hasPermission('applications.verify')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Verification);
    }

    public function evaluate(User $user, Application $application): bool
    {
        if (! $user->hasPermission('applications.evaluate')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Evaluation);
    }

    public function approve(User $user, Application $application): bool
    {
        if (! $user->hasPermission('applications.approve')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return WorkflowStaff::isAssignedToStep($user->id, WorkflowStep::Approval);
    }
}
