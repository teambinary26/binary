<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $applicant = $request->user()->applicant()->with(['profile', 'primaryAddress'])->first();

        return Inertia::render('Applicant/Profile', [
            'applicant' => \App\Support\CamData::applicant($applicant),
            'barangays' => config('cams.barangays'),
        ]);
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $applicant = $request->user()->applicant;

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'sex' => ['required', 'in:male,female'],
            'contact_number' => ['required', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'beneficiary_type' => ['required', 'in:student,non_student'],
            'school_name' => ['nullable', 'string', 'max:150'],
            'course_or_program' => ['nullable', 'string', 'max:150'],
            'year_level' => ['nullable', 'string', 'max:50'],
        ]);

        $applicant->update([
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'sex' => $data['sex'],
            'contact_number' => $data['contact_number'],
        ]);

        $request->user()->update(['name' => $data['full_name']]);

        $applicant->profile->update([
            'beneficiary_type' => $data['beneficiary_type'],
            'school_name' => $data['beneficiary_type'] === 'student' ? ($data['school_name'] ?? null) : null,
            'course_or_program' => $data['beneficiary_type'] === 'student' ? ($data['course_or_program'] ?? null) : null,
            'year_level' => $data['beneficiary_type'] === 'student' ? ($data['year_level'] ?? null) : null,
        ]);

        $applicant->primaryAddress->update([
            'street' => $data['street'],
            'barangay' => $data['barangay'],
            'municipality' => $data['municipality'],
            'province' => $data['province'],
        ]);

        $audit->log('updated', 'Applicant profile updated.', user: $request->user());

        return back()->with('success', 'Profile information has been updated.');
    }
}
