<?php

namespace App\Http\Middleware;

use App\Enums\WorkflowStep;
use App\Models\Permission;
use App\Models\SystemNotification;
use App\Models\WorkflowStaff;
use App\Support\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->isBrowserDocumentRequest($request)) {
            $request->headers->remove(Header::INERTIA);
            $request->headers->remove(Header::VERSION);
        }

        $response = parent::handle($request, $next);

        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Vary', 'X-Inertia, Accept, Accept-Encoding');

        return $response;
    }

    protected function isBrowserDocumentRequest(Request $request): bool
    {
        $dest = strtolower((string) $request->headers->get('Sec-Fetch-Dest', ''));
        $mode = strtolower((string) $request->headers->get('Sec-Fetch-Mode', ''));

        return $dest === 'document' || $mode === 'navigate';
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing(['role.permissions', 'applicant']);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'office' => $user->office,
                    'employee_no' => $user->employee_no,
                    'is_staff' => $user->isStaff(),
                    'can_access_admin' => $user->canAccessAdmin(),
                    'is_applicant' => $user->isApplicant(),
                    'is_admin' => $user->isAdmin(),
                    'is_super_admin' => $user->isAdmin(),
                    'role_name' => $user->role?->name,
                    'role_slug' => $user->role?->slug,
                    'permissions' => $user->isAdmin()
                        ? Permission::query()->pluck('slug')->values()->all()
                        : ($user->role?->permissions->pluck('slug')->values()->all() ?? []),
                    'workflow_steps' => $user->isAdmin()
                        ? collect(WorkflowStep::ordered())->pluck('value')->all()
                        : WorkflowStaff::query()
                            ->where('user_id', $user->id)
                            ->pluck('workflow_step')
                            ->map(fn ($s) => $s instanceof WorkflowStep ? $s->value : $s)
                            ->values()
                            ->all(),
                    'applicant' => $user->applicant ? [
                        'id' => $user->applicant->id,
                        'applicant_no' => $user->applicant->applicant_no,
                        'full_name' => $user->applicant->full_name,
                    ] : null,
                ] : null,
            ],
            'gov' => gov(),
            'turnstile' => [
                'site_key' => (string) config('services.turnstile.site_key'),
                'enabled' => ! TurnstileVerifier::canBypass($request),
            ],
            'clock' => [
                'date' => now()->timezone(config('app.timezone'))->format('l, d F Y'),
                'datetime' => now()->timezone(config('app.timezone'))->format('d F Y, h:i A'),
                'year' => (int) now()->timezone(config('app.timezone'))->format('Y'),
            ],
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'unreadNotificationCount' => $user
                ? SystemNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count()
                : 0,
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
