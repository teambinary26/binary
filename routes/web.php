<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Applicant;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Site\AnnouncementController as PublicAnnouncementController;
use App\Http\Controllers\Site\ApplicationStatusController;
use App\Http\Controllers\Site\ApplyController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ProgramController as PublicProgramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('site.home');
Route::get('/programs', [PublicProgramController::class, 'index'])->name('site.programs.index');
Route::get('/programs/{program:slug}', [PublicProgramController::class, 'show'])->name('site.programs.show');
Route::get('/programs/{program:slug}/apply', [ApplyController::class, 'create'])->name('site.apply.create');
Route::post('/programs/{program:slug}/apply/turnstile', [ApplyController::class, 'verifyTurnstile'])->middleware('throttle:20,1')->name('site.apply.turnstile');
Route::post('/programs/{program:slug}/apply/otp', [ApplyController::class, 'sendOtp'])->middleware('throttle:5,1')->name('site.apply.otp');
Route::post('/programs/{program:slug}/apply', [ApplyController::class, 'store'])->middleware('throttle:10,1')->name('site.apply.store');
Route::get('/apply/success', [ApplyController::class, 'success'])->name('site.apply.success');
Route::get('/how-to-apply', [HomeController::class, 'howToApply'])->name('site.how-to-apply');
Route::get('/requirements', [HomeController::class, 'requirements'])->name('site.requirements');
Route::get('/announcements', [PublicAnnouncementController::class, 'index'])->name('site.announcements.index');
Route::get('/announcements/{announcement}', [PublicAnnouncementController::class, 'show'])->name('site.announcements.show');
Route::get('/contact', [HomeController::class, 'contact'])->name('site.contact');
Route::get('/application-status', [ApplicationStatusController::class, 'show'])->name('site.status');
Route::post('/contact', function (Request $request) {
    $request->validate([
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'email'],
        'subject' => ['required', 'string', 'max:150'],
        'message' => ['required', 'string', 'max:2000'],
    ]);

    return back()->with('success', 'Your message has been recorded. The MSWDO will respond during office hours.');
})->name('site.contact.submit');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'applicant'])->prefix('applicant')->name('applicant.')->group(function () {
    Route::get('/dashboard', Applicant\DashboardController::class)->name('dashboard');
    Route::get('/programs', [Applicant\ProgramController::class, 'index'])->name('programs.index');
    Route::get('/applications', [Applicant\ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [Applicant\ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/applications/{application}/documents/{document}', [Applicant\ApplicationController::class, 'download'])->name('applications.documents.download');
    Route::get('/applications/{application}/documents/{document}/preview', [Applicant\ApplicationController::class, 'preview'])->name('applications.documents.preview');
    Route::get('/apply/{program:slug}', [Applicant\ApplicationController::class, 'start'])->name('apply.start');
    Route::get('/applications/{application}/eligibility', [Applicant\ApplicationController::class, 'eligibility'])->name('apply.eligibility');
    Route::post('/applications/{application}/eligibility', [Applicant\ApplicationController::class, 'storeEligibility'])->name('apply.eligibility.store');
    Route::get('/applications/{application}/form', [Applicant\ApplicationController::class, 'form'])->name('apply.form');
    Route::post('/applications/{application}/form', [Applicant\ApplicationController::class, 'storeForm'])->name('apply.form.store');
    Route::get('/applications/{application}/documents', [Applicant\ApplicationController::class, 'documents'])->name('apply.documents');
    Route::post('/applications/{application}/documents', [Applicant\ApplicationController::class, 'storeDocument'])->name('apply.documents.store');
    Route::get('/applications/{application}/review', [Applicant\ApplicationController::class, 'review'])->name('apply.review');
    Route::post('/applications/{application}/submit', [Applicant\ApplicationController::class, 'submit'])->name('apply.submit');
    Route::get('/profile', [Applicant\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Applicant\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/notifications', [Applicant\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [Applicant\NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', Admin\DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    Route::middleware('permission:applications.view')->group(function () {
        Route::get('/applications', [Admin\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{application}', [Admin\ApplicationController::class, 'show'])->name('applications.show');
        Route::get('/applications/{application}/documents/{document}', [Admin\ApplicationController::class, 'download'])->name('applications.documents.download');
        Route::get('/applications/{application}/documents/{document}/preview', [Admin\ApplicationController::class, 'preview'])->name('applications.documents.preview');
        Route::put('/applications/{application}/assign', [Admin\ApplicationController::class, 'assign'])->name('applications.assign');
    });

    Route::delete('/applications/{application}', [Admin\ApplicationController::class, 'destroy'])
        ->middleware('permission:applications.manage')->name('applications.destroy');

    Route::post('/applications/{application}/documents/{document}/verify', [Admin\ApplicationController::class, 'verifyDocument'])
        ->middleware('permission:applications.verify')->name('applications.documents.verify');
    Route::post('/applications/{application}/documents/{document}/ocr', [Admin\ApplicationController::class, 'processOcr'])
        ->middleware('permission:applications.verify')->name('applications.documents.ocr');
    Route::put('/applications/{application}/documents/{document}/ocr', [Admin\ApplicationController::class, 'updateOcrFields'])
        ->middleware('permission:applications.verify')->name('applications.documents.ocr.update');
    Route::post('/applications/{application}/complete-verification', [Admin\ApplicationController::class, 'completeVerification'])
        ->middleware('permission:applications.verify')->name('applications.complete-verification');
    Route::post('/applications/{application}/scan-eligibility', [Admin\ApplicationController::class, 'scanEligibility'])
        ->middleware('permission:applications.evaluate')->name('applications.scan-eligibility');
    Route::post('/applications/{application}/evaluate', [Admin\ApplicationController::class, 'evaluate'])
        ->middleware('permission:applications.evaluate')->name('applications.evaluate');
    Route::post('/applications/{application}/complete-evaluation', [Admin\ApplicationController::class, 'completeEvaluation'])
        ->middleware('permission:applications.evaluate')->name('applications.complete-evaluation');
    Route::post('/applications/{application}/decide', [Admin\ApplicationController::class, 'decide'])
        ->middleware('permission:applications.approve')->name('applications.decide');
    Route::post('/applications/{application}/approve-applicant', [Admin\ApplicationController::class, 'approveApplicant'])
        ->middleware('permission:applications.approve')->name('applications.approve-applicant');
    Route::post('/applications/{application}/reject-applicant', [Admin\ApplicationController::class, 'rejectApplicant'])
        ->middleware('permission:applications.approve')->name('applications.reject-applicant');

    Route::get('/workflow', [Admin\WorkflowController::class, 'index'])->middleware('permission:applications.view')->name('workflow.index');
    Route::get('/verification', [Admin\VerificationController::class, 'index'])->middleware('permission:applications.verify')->name('verification.index');
    Route::get('/evaluation', [Admin\EvaluationController::class, 'index'])->middleware('permission:applications.evaluate')->name('evaluation.index');
    Route::get('/approvals', [Admin\ApprovalController::class, 'index'])->middleware('permission:applications.approve')->name('approvals.index');

    Route::middleware('permission:programs.view')->group(function () {
        Route::get('/programs', [Admin\ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/create', [Admin\ProgramController::class, 'create'])->middleware('permission:programs.manage')->name('programs.create');
        Route::post('/programs', [Admin\ProgramController::class, 'store'])->middleware('permission:programs.manage')->name('programs.store');
        Route::get('/programs/{program}/edit', [Admin\ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/programs/{program}', [Admin\ProgramController::class, 'update'])->middleware('permission:programs.manage')->name('programs.update');
        Route::delete('/programs/{program}', [Admin\ProgramController::class, 'destroy'])->middleware('permission:programs.manage')->name('programs.destroy');
        Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [Admin\CategoryController::class, 'store'])->middleware('permission:programs.manage')->name('categories.store');
        Route::put('/categories/{category}', [Admin\CategoryController::class, 'update'])->middleware('permission:programs.manage')->name('categories.update');
        Route::get('/requirements', [Admin\RequirementController::class, 'index'])->name('requirements.index');
        Route::post('/requirements', [Admin\RequirementController::class, 'store'])->middleware('permission:programs.manage')->name('requirements.store');
        Route::delete('/requirements/{requirement}', [Admin\RequirementController::class, 'destroy'])->middleware('permission:programs.manage')->name('requirements.destroy');
    });

    Route::middleware('permission:applicants.view')->group(function () {
        Route::get('/applicants', [Admin\ApplicantController::class, 'index'])->name('applicants.index');
        Route::get('/applicants/{applicant}', [Admin\ApplicantController::class, 'show'])->name('applicants.show');
        Route::delete('/applicants/{applicant}', [Admin\ApplicantController::class, 'destroy'])->middleware('permission:applicants.manage')->name('applicants.destroy');
    });

    Route::middleware('permission:releases.view')->group(function () {
        Route::get('/releases', [Admin\ReleaseController::class, 'index'])->name('releases.index');
        Route::get('/releases/released', [Admin\ReleaseController::class, 'released'])->name('releases.released');
        Route::post('/releases/schedule', [Admin\ReleaseController::class, 'schedule'])->middleware('permission:releases.manage')->name('releases.schedule');
        Route::post('/releases/schedules/reschedule', [Admin\ReleaseController::class, 'rescheduleMany'])->middleware('permission:releases.manage')->name('releases.reschedule-many');
        Route::put('/releases/schedules/{schedule}', [Admin\ReleaseController::class, 'reschedule'])->middleware('permission:releases.manage')->name('releases.reschedule');
        Route::post('/releases/record', [Admin\ReleaseController::class, 'record'])->middleware('permission:releases.manage')->name('releases.record');
        Route::get('/releases/verify', [Admin\ReleaseController::class, 'verifyForm'])->middleware('permission:releases.verify')->name('releases.verify');
        Route::post('/releases/verify', [Admin\ReleaseController::class, 'verify'])->middleware('permission:releases.verify')->name('releases.verify.store');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/applications', [Admin\ReportController::class, 'applications'])->name('reports.applications');
        Route::get('/reports/financial', [Admin\ReportController::class, 'financial'])->name('reports.financial');
        Route::get('/reports/beneficiaries', [Admin\ReportController::class, 'beneficiaries'])->name('reports.beneficiaries');
    });

    Route::middleware('permission:announcements.view')->group(function () {
        Route::get('/announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [Admin\AnnouncementController::class, 'create'])->middleware('permission:announcements.manage')->name('announcements.create');
        Route::post('/announcements', [Admin\AnnouncementController::class, 'store'])->middleware('permission:announcements.manage')->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [Admin\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [Admin\AnnouncementController::class, 'update'])->middleware('permission:announcements.manage')->name('announcements.update');
        Route::delete('/announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->middleware('permission:announcements.manage')->name('announcements.destroy');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [Admin\UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
        Route::post('/users', [Admin\UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
        Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [Admin\UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->middleware('permission:users.manage')->name('users.destroy');
    });

    Route::get('/roles', [Admin\RoleController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');
    Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');
    Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit-logs.index');
    Route::get('/settings', [Admin\SettingController::class, 'edit'])->middleware('permission:settings.view')->name('settings.edit');
    Route::put('/settings', [Admin\SettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::put('/settings/workflow', [Admin\SettingController::class, 'updateWorkflow'])->middleware('permission:settings.manage')->name('settings.workflow');
});
