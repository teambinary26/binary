<?php

use App\Models\Application;
use App\Models\AssistanceProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('application report can be exported to excel and pdf', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()->firstOrFail();

    $excel = $this->actingAs($admin)->get(route('admin.reports.applications', ['export' => 'excel']));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))->toStartWith('application/vnd.ms-excel')
        ->and($excel->getContent())->toContain($application->application_no)
        ->and($excel->getContent())->toContain('Applicant');

    $pdf = $this->actingAs($admin)->get(route('admin.reports.applications', ['export' => 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
});

test('application report excel export respects program and status filters', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $application = Application::query()->firstOrFail();
    $other = Application::query()
        ->where('id', '!=', $application->id)
        ->where(function ($query) use ($application) {
            $query->where('assistance_program_id', '!=', $application->assistance_program_id)
                ->orWhere('status', '!=', $application->status);
        })
        ->first();

    $excel = $this->actingAs($admin)->get(route('admin.reports.applications', [
        'export' => 'excel',
        'program' => $application->assistance_program_id,
        'status' => $application->status->value,
    ]));

    $excel->assertOk();
    expect($excel->getContent())->toContain($application->application_no);

    if ($other && $other->assistance_program_id !== $application->assistance_program_id) {
        expect($excel->getContent())->not->toContain($other->application_no);
    }
});

test('financial report can be exported to excel and pdf', function () {
    $admin = User::query()->where('email', 'admin@nabua.gov.ph')->firstOrFail();
    $program = AssistanceProgram::query()
        ->whereHas('applications.releases')
        ->firstOrFail();

    $excel = $this->actingAs($admin)->get(route('admin.reports.financial', ['export' => 'excel']));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))->toStartWith('application/vnd.ms-excel')
        ->and($excel->getContent())->toContain($program->name)
        ->and($excel->getContent())->toContain('Total released');

    $pdf = $this->actingAs($admin)->get(route('admin.reports.financial', ['export' => 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
});
