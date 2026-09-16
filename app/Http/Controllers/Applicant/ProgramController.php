<?php

namespace App\Http\Controllers\Applicant;

use App\Enums\BeneficiaryType;
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
            ->where(function ($query) use ($type) {
                $query->where('beneficiary_type', BeneficiaryType::Both)
                    ->orWhere('beneficiary_type', $type);
            })
            ->orderBy('sort_order')
            ->paginate(12);

        return Inertia::render('Applicant/Programs', [
            'programs' => CamData::paginator($programs, fn ($program) => CamData::program($program)),
            'currentApplication' => $current ? [
                'id' => $current->id,
                'program_id' => $current->assistance_program_id,
                'program_name' => $current->program?->name,
                'can_edit' => $current->canBeEditedByApplicant(),
            ] : null,
        ]);
    }
}
