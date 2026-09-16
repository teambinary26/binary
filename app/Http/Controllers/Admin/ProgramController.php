<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BeneficiaryType;
use App\Http\Controllers\Controller;
use App\Models\AssistanceProgram;
use App\Models\ProgramCategory;
use App\Services\AuditService;
use App\Services\EligibilityAssessmentService;
use App\Support\CamData;
use App\Support\OcrFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(): Response
    {
        $programs = AssistanceProgram::query()->with('category')->withCount('applications')->orderBy('sort_order')->paginate(20);

        return Inertia::render('Admin/Programs/Index', [
            'programs' => CamData::paginator($programs, fn ($p) => CamData::program($p)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Programs/Form', [
            'program' => null,
            'categories' => ProgramCategory::query()->orderBy('name')->get(['id', 'name']),
            'ocrFields' => OcrFields::catalog(),
            'eligibilityFields' => EligibilityAssessmentService::fieldCatalog(),
            'eligibilityOperators' => EligibilityAssessmentService::operatorCatalog(),
            'eligibilityCheckModes' => EligibilityAssessmentService::checkModeCatalog(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $program = AssistanceProgram::query()->create($this->validated($request));
        $this->syncRelated($program, $request);
        $audit->log('created', 'Created assistance program '.$program->name, subject: $program);

        return redirect()->route('admin.programs.index')->with('success', 'Program created.');
    }

    public function edit(AssistanceProgram $program): Response
    {
        $program->load(['requirements', 'formFields', 'eligibilityRules', 'category']);

        return Inertia::render('Admin/Programs/Form', [
            'program' => array_merge(CamData::program($program, true), [
                'program_category_id' => $program->program_category_id,
            ]),
            'categories' => ProgramCategory::query()->orderBy('name')->get(['id', 'name']),
            'ocrFields' => OcrFields::catalog(),
            'eligibilityFields' => EligibilityAssessmentService::fieldCatalog(),
            'eligibilityOperators' => EligibilityAssessmentService::operatorCatalog(),
            'eligibilityCheckModes' => EligibilityAssessmentService::checkModeCatalog(),
        ]);
    }

    public function update(Request $request, AssistanceProgram $program, AuditService $audit): RedirectResponse
    {
        $program->update($this->validated($request, $program->id));
        $this->syncRelated($program, $request);
        $audit->log('updated', 'Updated assistance program '.$program->name, subject: $program);

        return back()->with('success', 'Program updated.');
    }

    public function destroy(AssistanceProgram $program, AuditService $audit): RedirectResponse
    {
        if ($program->applications()->exists()) {
            return back()->with('error', 'This program cannot be deleted while application records still exist.');
        }

        $this->authorize('delete', $program);

        $name = $program->name;
        $program->delete();
        $audit->log('deleted', 'Deleted assistance program '.$name);

        return redirect()->route('admin.programs.index')->with('success', 'Program deleted.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'program_category_id' => ['required', 'exists:program_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:assistance_programs,code'.($id ? ','.$id : '')],
            'description' => ['required', 'string'],
            'eligibility' => ['required', 'string'],
            'beneficiary_type' => ['required', 'in:student,non_student,both'],
            'amount_type' => ['required', 'in:fixed,up_to,variable'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
            'is_open' => ['nullable', 'boolean'],
            'open_from' => ['nullable', 'date'],
            'open_until' => ['nullable', 'date'],
            'slot_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['is_open'] = $request->boolean('is_open');
        $data['slug'] = Str::slug($data['name']);
        $data['beneficiary_type'] = BeneficiaryType::from($data['beneficiary_type']);

        return $data;
    }

    private function syncRelated(AssistanceProgram $program, Request $request): void
    {
        if ($request->has('requirements')) {
            $program->requirements()->delete();
            foreach ($request->input('requirements', []) as $index => $row) {
                if (blank($row['name'] ?? null)) {
                    continue;
                }
                $ocrFields = collect($row['ocr_fields'] ?? [])
                    ->filter(fn ($key) => in_array($key, OcrFields::keys(), true))
                    ->values()
                    ->all();

                $program->requirements()->create([
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'is_required' => ! empty($row['is_required']),
                    'ocr_fields' => $ocrFields,
                    'sort_order' => $index + 1,
                ]);
            }
        }

        if ($request->has('fields')) {
            $program->formFields()->delete();
            foreach ($request->input('fields', []) as $index => $row) {
                if (blank($row['label'] ?? null)) {
                    continue;
                }
                $options = filled($row['options'] ?? null)
                    ? array_values(array_filter(array_map('trim', explode(',', $row['options']))))
                    : null;
                $program->formFields()->create([
                    'name' => Str::slug($row['name'] ?? $row['label'], '_'),
                    'label' => $row['label'],
                    'type' => $row['type'] ?? 'text',
                    'options' => $options,
                    'is_required' => ! empty($row['is_required']),
                    'help_text' => $row['help_text'] ?? null,
                    'sort_order' => $index + 1,
                ]);
            }
        }

        if ($request->has('rules')) {
            $program->eligibilityRules()->delete();
            foreach ($request->input('rules', []) as $index => $row) {
                if (blank($row['label'] ?? null)) {
                    continue;
                }
                $checkMode = in_array($row['check_mode'] ?? null, EligibilityAssessmentService::checkModeKeys(), true)
                    ? $row['check_mode']
                    : 'ocr';
                $field = $checkMode === 'ocr' && in_array($row['field'] ?? null, EligibilityAssessmentService::fieldKeys(), true)
                    ? $row['field']
                    : null;
                $operator = $checkMode === 'ocr' && in_array($row['operator'] ?? null, EligibilityAssessmentService::operatorKeys(), true)
                    ? $row['operator']
                    : null;

                $program->eligibilityRules()->create([
                    'label' => $row['label'],
                    'field' => $field,
                    'operator' => $field ? $operator : null,
                    'value' => $checkMode === 'ocr' && filled($row['value'] ?? null) ? $row['value'] : null,
                    'check_mode' => $checkMode,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }
}
