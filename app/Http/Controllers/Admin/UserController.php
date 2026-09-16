<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CouldNotDeleteUserException;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserDeletionService;
use App\Support\CamData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $currentId = $request->user()?->id;

        $mapUser = fn (User $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'role' => $row->role?->name,
            'office' => $row->office,
            'is_active' => $row->is_active,
            'is_staff' => $row->isStaff(),
            'is_applicant' => $row->isApplicant(),
            'can_delete' => $row->id !== $currentId,
            'last_login_at' => gov_datetime($row->last_login_at),
        ];

        $byRole = fn (string $slug, string $pageName) => User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->where('slug', $slug))
            ->orderBy('name')
            ->paginate(10, ['*'], $pageName);

        return Inertia::render('Admin/Users/Index', [
            'administrators' => CamData::paginator($byRole('administrator', 'admin_page'), $mapUser),
            'staff' => CamData::paginator($byRole('staff', 'staff_page'), $mapUser),
            'applicants' => CamData::paginator($byRole('applicant', 'applicant_page'), $mapUser),
            'roles' => $this->staffRoles(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.users.index');
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::query()->create($data);
        $audit->log('created', 'Created staff user '.$user->email, subject: $user);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'staff' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_no' => $user->employee_no,
                'office' => $user->office,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
            ],
            'roles' => $this->staffRoles(),
        ]);
    }

    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $this->validated($request, $user->id);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);
        $audit->log('updated', 'Updated staff user '.$user->email, subject: $user);

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user, UserDeletionService $deletion, AuditService $audit): RedirectResponse
    {
        $label = $user->name.' ('.$user->email.')';
        $wasApplicant = $user->isApplicant();

        try {
            $deletion->delete($user, $request->user());
        } catch (CouldNotDeleteUserException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $audit->log('deleted', 'Deleted system user '.$label);

        return redirect()
            ->route('admin.users.index')
            ->with('success', $wasApplicant
                ? 'Applicant account and all connected records were deleted.'
                : 'User deleted.');
    }

    private function staffRoles(): array
    {
        return Role::query()
            ->whereIn('slug', ['administrator', 'staff'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'.($id ? ','.$id : '')],
            'employee_no' => ['nullable', 'string', 'max:50'],
            'office' => ['nullable', 'string', 'max:150'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->whereIn('slug', ['administrator', 'staff']))],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
