<?php

namespace App\Policies;

use App\Models\AssistanceProgram;
use App\Models\User;

class AssistanceProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('programs.view');
    }

    public function view(User $user, AssistanceProgram $program): bool
    {
        return $user->hasPermission('programs.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('programs.manage');
    }

    public function update(User $user, AssistanceProgram $program): bool
    {
        return $user->hasPermission('programs.manage');
    }

    public function delete(User $user, AssistanceProgram $program): bool
    {
        return $user->hasPermission('programs.manage') && $program->applications()->doesntExist();
    }
}
