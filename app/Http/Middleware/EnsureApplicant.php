<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->isApplicant() || ! $user->applicant) {
            abort(403, 'This module is available only to registered applicants.');
        }

        return $next($request);
    }
}
