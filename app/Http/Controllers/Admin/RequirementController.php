<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistanceProgram;
use App\Models\ProgramRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequirementController extends Controller
{
    public function index(): Response
    {
        $programs = AssistanceProgram::query()->with('requirements')->orderBy('name')->get();

        return Inertia::render('Admin/Requirements/Index', [
            'programs' => $programs->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'requirements' => $p->requirements->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'is_required' => $r->is_required,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assistance_program_id' => ['required', 'exists:assistance_programs,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        $data['is_required'] = $request->boolean('is_required', true);
        $data['sort_order'] = ProgramRequirement::query()->where('assistance_program_id', $data['assistance_program_id'])->max('sort_order') + 1;
        ProgramRequirement::query()->create($data);

        return back()->with('success', 'Requirement added.');
    }

    public function destroy(ProgramRequirement $requirement): RedirectResponse
    {
        $requirement->delete();

        return back()->with('success', 'Requirement removed.');
    }
}
