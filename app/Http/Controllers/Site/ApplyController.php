<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssistanceProgram;
use App\Services\PublicApplicationService;
use App\Support\CamData;
use App\Support\ProgramFormFieldRules;
use App\Support\TurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApplyController extends Controller
{
    public function create(AssistanceProgram $program): Response|RedirectResponse
    {
        if (! $program->isCurrentlyOpen()) {
            return redirect()
                ->route('site.programs.show', $program)
                ->with('error', 'This program is not currently open for applications.');
        }

        $program->load(['category', 'eligibilityRules', 'requirements', 'formFields']);

        if (TurnstileVerifier::canBypass(request())) {
            TurnstileVerifier::markPassed(request());
        }

        return Inertia::render('Public/Programs/Apply', [
            'program' => CamData::program($program, true),
            'turnstilePassed' => TurnstileVerifier::passed(request()),
        ]);
    }

    public function verifyTurnstile(Request $request, AssistanceProgram $program): JsonResponse
    {
        if (! $program->isCurrentlyOpen()) {
            throw ValidationException::withMessages([
                'program' => 'This program is not currently open for applications.',
            ]);
        }

        if (TurnstileVerifier::canBypass($request)) {
            TurnstileVerifier::markPassed($request);

            return response()->json([
                'ok' => true,
                'message' => 'Security check passed. You may continue with your application.',
            ]);
        }

        $data = $request->validate([
            'turnstile_token' => ['required', 'string'],
        ], [
            'turnstile_token.required' => 'Please complete the security check before continuing.',
        ]);

        if (! TurnstileVerifier::verify($data['turnstile_token'], $request->ip())) {
            TurnstileVerifier::clear($request);

            throw ValidationException::withMessages([
                'turnstile_token' => 'The security check failed. Please complete it again.',
            ]);
        }

        TurnstileVerifier::markPassed($request);

        return response()->json([
            'ok' => true,
            'message' => 'Security check passed. You may continue with your application.',
        ]);
    }

    public function sendOtp(Request $request, AssistanceProgram $program, PublicApplicationService $apply): JsonResponse
    {
        if (! $program->isCurrentlyOpen()) {
            throw ValidationException::withMessages([
                'program' => 'This program is not currently open for applications.',
            ]);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'turnstile_token' => ['nullable', 'string'],
        ]);

        TurnstileVerifier::assertPassed($request, $data['turnstile_token'] ?? null);

        $apply->sendOtp($data['email'], $data['full_name']);

        return response()->json([
            'message' => 'A verification code was sent to '.$data['email'].'.',
        ]);
    }

    public function store(Request $request, AssistanceProgram $program, PublicApplicationService $apply): RedirectResponse
    {
        $program->loadMissing('formFields');

        $data = $request->validate(array_merge([
            'full_name' => ['required', 'string', 'max:150'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'sex' => ['required', 'in:male,female'],
            'street' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150'],
            'mother_name' => ['required', 'string', 'max:150'],
            'mother_occupation' => ['required', 'string', 'max:150'],
            'father_name' => ['required', 'string', 'max:150'],
            'father_occupation' => ['required', 'string', 'max:150'],
            'is_pwd' => ['required', 'boolean'],
            'pwd_type' => ['nullable', 'required_if:is_pwd,1,true', 'in:'.implode(',', array_keys(config('cams.pwd_types')))],
            'pwd_type_detail' => ['nullable', 'required_if:pwd_type,other', 'string', 'max:150'],
            'beneficiary_type' => ['required', 'in:student,non_student'],
            'school_name' => ['nullable', 'required_if:beneficiary_type,student', 'string', 'max:150'],
            'course_or_program' => ['nullable', 'required_if:beneficiary_type,student', 'string', 'max:150'],
            'year_level' => ['nullable', 'required_if:beneficiary_type,student', 'string', 'max:50'],
            'otp' => ['required', 'string', 'size:6'],
            'eligibility_confirmed' => ['accepted'],
            'turnstile_token' => ['nullable', 'string'],
        ], ProgramFormFieldRules::rules($program, 'answers.', $request->input('beneficiary_type'))), array_merge([
            'eligibility_confirmed.accepted' => 'You must confirm that you meet the eligibility conditions before continuing.',
        ], ProgramFormFieldRules::messages($program)));

        TurnstileVerifier::assertPassed($request, $data['turnstile_token'] ?? null);

        $data['is_pwd'] = $request->boolean('is_pwd');
        if (! $data['is_pwd']) {
            $data['pwd_type'] = null;
            $data['pwd_type_detail'] = null;
        } elseif (($data['pwd_type'] ?? null) !== 'other') {
            $data['pwd_type_detail'] = null;
        }

        if (($data['beneficiary_type'] ?? null) === 'non_student') {
            $data['school_name'] = null;
            $data['course_or_program'] = null;
            $data['year_level'] = null;
            if (is_array($data['answers'] ?? null)) {
                foreach (ProgramFormFieldRules::studentFieldNames() as $fieldName) {
                    $data['answers'][$fieldName] = null;
                }
            }
        }

        $application = $apply->submit($program, $data);

        return redirect()
            ->route('site.apply.success')
            ->with('apply_success', [
                'application_no' => $application->application_no,
                'program_name' => $program->name,
                'email' => $data['email'],
            ]);
    }

    public function success(Request $request): Response|RedirectResponse
    {
        $success = $request->session()->get('apply_success');

        if (! $success) {
            return redirect()->route('site.programs.index');
        }

        return Inertia::render('Public/Programs/ApplySuccess', [
            'result' => $success,
        ]);
    }
}
