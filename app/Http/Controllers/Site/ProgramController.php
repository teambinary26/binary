<?php

namespace App\Http\Controllers\Site;

use App\Enums\BeneficiaryType;
use App\Http\Controllers\Controller;
use App\Models\AssistanceProgram;
use App\Models\ProgramCategory;
use App\Support\CamData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $programs = AssistanceProgram::query()
            ->with('category')
            ->when($request->filled('category'), fn ($q) => $q->where('program_category_id', $request->integer('category')))
            ->when($request->filled('beneficiary'), fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('beneficiary_type', $request->string('beneficiary'))
                    ->orWhere('beneficiary_type', BeneficiaryType::Both->value);
            }))
            ->when($request->filled('availability'), function ($q) use ($request) {
                if ($request->string('availability') === 'open') {
                    $q->where('is_open', true);
                } elseif ($request->string('availability') === 'closed') {
                    $q->where('is_open', false);
                }
            })
            ->when($request->filled('q'), fn ($q) => $q->where(function ($query) use ($request) {
                $search = '%'.$request->string('q').'%';
                $query->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('code', 'like', $search);
            }))
            ->orderBy('sort_order')
            ->get();

        $categories = ProgramCategory::query()->where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Public/Programs/Index', [
            'programs' => $programs->map(fn ($p) => CamData::program($p))->values(),
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'group_label' => $c->groupLabel(),
            ])->values(),
            'filters' => $request->only(['category', 'beneficiary', 'availability', 'q']),
        ]);
    }

    public function show(AssistanceProgram $program): Response
    {
        $program->load(['category', 'requirements', 'eligibilityRules', 'formFields']);

        return Inertia::render('Public/Programs/Show', [
            'program' => CamData::program($program, true),
        ]);
    }
}
