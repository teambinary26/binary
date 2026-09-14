<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\AssistanceProgram;
use App\Support\CamData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $applicant = $request->user()->applicant;
        $type = $applicant->beneficiaryType();
        $current = $applicant->currentApplication();

        $programs = AssistanceProgram::query()
            ->with('category')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (AssistanceProgram $program) => $program->acceptsBeneficiary($type))
            ->map(fn ($program) => CamData::program($program))
            ->values();

        return Inertia::render('Applicant/Programs', [
            'programs' => $programs,
            'currentApplication' => $current ? [
                'id' => $current->id,
                'program_id' => $current->assistance_program_id,
                'program_name' => $current->program?->name,
                'can_edit' => $current->canBeEditedByApplicant(),
            ] : null,
        ]);
    }
}
