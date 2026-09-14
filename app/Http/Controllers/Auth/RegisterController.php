<?php

namespace App\Http\Controllers\Auth;

use App\Enums\BeneficiaryType;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'barangays' => config('cams.barangays'),
        ]);
    }

    public function store(Request $request, ApplicationService $applications, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'sex' => ['required', 'in:male,female'],
            'street' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'beneficiary_type' => ['required', 'in:student,non_student'],
        ]);

        $roleId = Role::query()->where('slug', 'applicant')->value('id');

        $user = DB::transaction(function () use ($data, $roleId, $applications) {
            $user = User::query()->create([
                'role_id' => $roleId,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $applicant = Applicant::query()->create([
                'user_id' => $user->id,
                'applicant_no' => $applications->generateNumber(gov('applicant_prefix', 'BEN'), 'applicants', 'applicant_no'),
                'full_name' => $data['full_name'],
                'date_of_birth' => $data['date_of_birth'],
                'sex' => $data['sex'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
            ]);

            $applicant->profile()->create([
                'beneficiary_type' => $data['beneficiary_type'] === 'student'
                    ? BeneficiaryType::Student
                    : BeneficiaryType::NonStudent,
            ]);

            $applicant->addresses()->create([
                'street' => $data['street'],
                'barangay' => $data['barangay'],
                'municipality' => $data['municipality'],
                'province' => $data['province'],
                'is_primary' => true,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $audit->log('created', 'Applicant account registered: '.$user->email, user: $user, request: $request);

        return redirect()->route('applicant.dashboard')->with('success', 'Your applicant account has been created. You may now apply for available assistance programs.');
    }
}
