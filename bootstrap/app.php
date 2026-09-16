<?php

use App\Http\Middleware\EnsureApplicant;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'staff' => EnsureStaff::class,
            'applicant' => EnsureApplicant::class,
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function ($request) {
            return $request->user()?->homePath() ?? '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
