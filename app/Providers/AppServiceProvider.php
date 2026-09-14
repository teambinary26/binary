<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Policies\ApplicationPolicy;
use App\Policies\AssistanceProgramPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(AssistanceProgram::class, AssistanceProgramPolicy::class);

        Gate::before(function ($user, $ability) {
            if ($user && $user->isAdmin()) {
                return true;
            }

            return null;
        });
    }
}
