<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkflowStep;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkflowStaff;
use App\Services\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        $keys = ['agency', 'lgu', 'province', 'address', 'phone', 'email', 'office_hours'];
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = SystemSetting::getValue($key, config('cams.'.$key));
        }

        $assignments = WorkflowStaff::allAssignments();

        $workflowSteps = collect(WorkflowStep::ordered())->map(function (WorkflowStep $step) use ($assignments) {
            $assigned = $assignments->where('workflow_step', $step)->values();

            return [
                'key' => $step->value,
                'label' => $step->label(),
                'description' => $step->description(),
                'order' => $step->order(),
                'assigned_user_ids' => $assigned->pluck('user_id')->values(),
                'assigned_user_names' => $assigned->map(fn ($row) => $row->user?->name)->filter()->values(),
            ];
        });

        $staff = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'applicant'))
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->name,
            ])
            ->values();

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => $settings,
            'workflowSteps' => $workflowSteps,
            'staff' => $staff,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agency' => ['required', 'string', 'max:200'],
            'lgu' => ['required', 'string', 'max:150'],
            'province' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email'],
            'office_hours' => ['required', 'string', 'max:150'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::setValue($key, $value);
        }

        return back()->with('success', 'System settings updated.');
    }

    public function updateWorkflow(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.workflow_step' => ['required', 'in:verification,evaluation,approval'],
            'assignments.*.user_ids' => ['nullable', 'array'],
            'assignments.*.user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        foreach ($data['assignments'] as $assignment) {
            $ids = collect($assignment['user_ids'] ?? [])->filter()->unique()->values();

            WorkflowStaff::query()
                ->where('workflow_step', $assignment['workflow_step'])
                ->when($ids->isNotEmpty(), fn ($query) => $query->whereNotIn('user_id', $ids))
                ->delete();

            foreach ($ids as $userId) {
                WorkflowStaff::query()->firstOrCreate([
                    'workflow_step' => $assignment['workflow_step'],
                    'user_id' => $userId,
                ]);
            }
        }

        $updated = app(ApplicationService::class)->syncOpenAssignments();
        $message = 'Workflow staff assignments updated.';

        if ($updated > 0) {
            $message .= ' '.$updated.' in-progress application'.($updated === 1 ? ' was' : 's were').' reassigned to the current workflow staff.';
        }

        return back()->with('success', $message);
    }
}
